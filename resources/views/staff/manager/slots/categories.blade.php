<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.slots.index') }}">Parking slots</a> / Zone category</p>
  <h1>Zone category</h1>
  <p class="manager-page-lede">Classify each existing zone as Car or Motorcycle. All slots in that zone inherit the category.</p>
</section>

<form id="zone-category-form" method="POST" action="{{ route('manager.slots.categories.update') }}">
  @csrf
  @method('PATCH')
<div class="manager-table-wrap">
  <table class="manager-table">
    <thead><tr><th>Zone</th><th>Slots</th><th>Vehicle type</th></tr></thead>
    <tbody>
      @forelse ($zones as $item)
        <tr>
          <th scope="row">{{ $item['zone'] }}</th>
          <td>{{ $item['slots'] }} / 16</td>
          <td>
            <select name="categories[{{ $item['zone'] }}]" aria-label="Vehicle type for {{ $item['zone'] }}">
                @foreach ($vehicleTypes as $vehicleType)
                  <option value="{{ $vehicleType }}" @selected($item['vehicle_type'] === $vehicleType)>{{ $vehicleType }}</option>
                @endforeach
            </select>
          </td>
        </tr>
      @empty
        <tr><td class="manager-table-empty" colspan="3">Add a parking slot first to create a zone category.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
</form>
<div class="manager-form-actions">
  <a class="btn ghost sm" href="{{ route('manager.slots.index') }}">Exit</a>
  @if ($zones->isNotEmpty())
    <button class="btn sm" type="submit" form="zone-category-form">Save all and exit</button>
  @endif
</div>
