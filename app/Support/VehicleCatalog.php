<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The vehicle model list that follows the chosen vehicle type, and the shared form check for it.
 * "Others" on the model or the color opens a text box so the driver can type what the list does not have.
 */
class VehicleCatalog
{
    public const OTHERS = 'Others';

    /** @var array<string, list<string>> */
    public const MODELS = [
        'Car' => [
            'Toyota Vios', 'Toyota Wigo', 'Toyota Corolla Altis', 'Toyota Camry', 'Honda City', 'Honda Civic', 'Honda Brio',
            'Mitsubishi Mirage', 'Mitsubishi Mirage G4', 'Nissan Almera', 'Suzuki Dzire', 'Suzuki Celerio', 'Hyundai Accent',
            'Kia Soluto', 'Kia Picanto', 'Mazda 2', 'Mazda 3', 'Ford Mustang',
        ],
        'SUV' => [
            'Toyota Fortuner', 'Toyota Rush', 'Toyota Raize', 'Toyota Corolla Cross', 'Mitsubishi Montero Sport', 'Mitsubishi Xforce',
            'Ford Everest', 'Ford Territory', 'Nissan Terra', 'Honda CR-V', 'Honda BR-V', 'Honda HR-V', 'Hyundai Tucson',
            'Hyundai Creta', 'Suzuki Jimny', 'Suzuki XL7', 'Kia Seltos', 'Kia Sportage', 'Mazda CX-5', 'Chevrolet Trailblazer',
            'Isuzu mu-X', 'Subaru Forester', 'Geely Coolray', 'Chery Tiggo',
        ],
        'Van' => [
            'Toyota Hiace', 'Toyota Innova', 'Toyota Avanza', 'Toyota Veloz', 'Mitsubishi Xpander', 'Mitsubishi L300', 'Nissan Urvan',
            'Hyundai Staria', 'Hyundai Starex', 'Suzuki Ertiga', 'Kia Carnival', 'Foton View', 'Honda Odyssey',
        ],
        'Pickup' => [
            'Toyota Hilux', 'Ford Ranger', 'Isuzu D-Max', 'Nissan Navara', 'Mitsubishi Strada', 'Mazda BT-50', 'Chevrolet Colorado',
            'GAC GS3 Pickup', 'Great Wall Poer',
        ],
        'Motorcycle' => [
            'Honda Click', 'Honda Beat', 'Honda ADV 160', 'Honda PCX', 'Honda TMX', 'Honda XRM', 'Honda CB150R', 'Yamaha Mio',
            'Yamaha NMAX', 'Yamaha Aerox', 'Yamaha Sniper', 'Yamaha XSR', 'Suzuki Raider', 'Suzuki Burgman', 'Suzuki Gixxer',
            'Kawasaki Rouser', 'Kawasaki Ninja', 'Vespa Sprint', 'Royal Enfield Classic',
        ],
    ];

    /** What the form's JavaScript reads: type => models, each list ending with Others. */
    public static function forForm(): array
    {
        $out = [];

        foreach (Vehicle::DRIVER_TYPES as $type) {
            $out[$type] = array_merge(self::MODELS[$type] ?? [], [self::OTHERS]);
        }

        return $out;
    }

    /** True when the model is in the list for that type. */
    public static function isListed(?string $type, ?string $model): bool
    {
        return in_array($model, self::MODELS[(string) $type] ?? [], true);
    }

    /** True for the "Other" a vehicle saved before this list existed, and for "Others". */
    public static function isOtherColor(?string $color): bool
    {
        return in_array($color, ['Other', self::OTHERS], true);
    }

    /** The colors a driver can pick, ending with Others. */
    public static function colors(): array
    {
        return array_merge(array_values(array_diff(Vehicle::COLORS, ['Other'])), [self::OTHERS]);
    }

    /**
     * The values a vehicle form should start with when editing: a saved model that is not in the list
     * opens as Others with its text in the box, and the same for a color that is not in the list.
     *
     * @return array{model:string,model_other:string,color:string,color_other:string}
     */
    public static function formValues(?Vehicle $vehicle): array
    {
        $model = (string) ($vehicle?->make_model ?? '');
        $color = (string) ($vehicle?->color ?? '');
        $listedModel = self::isListed($vehicle?->vehicle_type, $model);
        $listedColor = in_array($color, self::colors(), true) && $color !== self::OTHERS;

        return [
            'model' => $model === '' ? '' : ($listedModel ? $model : self::OTHERS),
            'model_other' => $model === '' || $listedModel ? '' : $model,
            'color' => $color === '' ? '' : ($listedColor ? $color : self::OTHERS),
            'color_other' => $color === '' || $listedColor || self::isOtherColor($color) ? '' : $color,
        ];
    }

    /**
     * Check the vehicle part of a form (type, model, color) and return clean values for the vehicles table.
     * The model must belong to the chosen type, or be typed in the box that appears for Others.
     *
     * @param  array<string, mixed>  $rules  Other fields of the same form.
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    public static function validate(Request $request, array $rules = [], array $messages = []): array
    {
        Rules::tidy($request, 'make_model_other', 'color_other');

        $validator = Validator::make($request->all(), $rules + [
            'vehicle_type' => ['required', Rule::in(Vehicle::DRIVER_TYPES)],
            'make_model' => ['required', 'string', 'max:60'],
            'make_model_other' => ['nullable', 'required_if:make_model,'.self::OTHERS, 'string', 'min:2', 'max:60', 'regex:'.Rules::MAKE_MODEL_PATTERN],
            'color' => ['required', Rule::in(self::colors())],
            'color_other' => ['nullable', 'required_if:color,'.self::OTHERS, 'string', 'min:3', 'max:30', 'regex:/^\p{L}[\p{L}\s\-\/]*$/u'],
        ], $messages + [
            'vehicle_type.required' => 'Choose your vehicle type.',
            'vehicle_type.in' => 'Choose a vehicle type from the list.',
            'make_model.required' => 'Choose your vehicle model.',
            'make_model_other.required_if' => 'Type your vehicle make and model, for example Toyota Vios.',
            'make_model_other.min' => 'Type your vehicle make and model, for example Toyota Vios.',
            'make_model_other.max' => 'The vehicle model can have up to 60 characters.',
            'make_model_other.regex' => 'Use letters and numbers for the vehicle model.',
            'color.required' => 'Choose your vehicle color.',
            'color.in' => 'Choose a color from the list.',
            'color_other.required_if' => 'Type the color of your vehicle.',
            'color_other.min' => 'Type the color of your vehicle.',
            'color_other.max' => 'The color can have up to 30 characters.',
            'color_other.regex' => 'Use letters for the color, for example Maroon.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $type = (string) $request->input('vehicle_type');
            $model = (string) $request->input('make_model');

            if (! $validator->errors()->has('make_model') && $model !== self::OTHERS && ! self::isListed($type, $model)) {
                $validator->errors()->add('make_model', 'Choose a model from the list, or choose Others and type it.');
            }
        });

        $data = $validator->validate();

        if ($data['make_model'] === self::OTHERS) {
            $data['make_model'] = $data['make_model_other'];
        }

        if ($data['color'] === self::OTHERS) {
            $data['color'] = ucfirst(mb_strtolower($data['color_other']));
        }

        unset($data['make_model_other'], $data['color_other']);

        return $data;
    }
}
