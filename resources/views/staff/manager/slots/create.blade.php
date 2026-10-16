<section class="manager-page-heading">
  <p class="eyebrow"><a class="manager-breadcrumb" href="{{ route('manager.slots.index') }}">Parking slots</a> / Add slot</p>
  <h1>Add parking slot</h1>
  <p class="manager-page-lede">New slots start available and are recorded in the activity log.</p>
</section>

<section class="manager-form-panel">
  <form method="POST" action="{{ route('manager.slots.store') }}">
    @csrf
    @include('staff.manager.slots._form')
    <div class="manager-form-actions">
      <a class="btn ghost sm" href="{{ route('manager.slots.index') }}">Cancel</a>
      <button class="btn sm" type="submit">Save slot</button>
    </div>
  </form>
</section>
