@props([
    'action' => null, // Route name or URL
    'checked' => false,
    'title' => null,
    'hidden' => [], // Array of hidden input fields ['name' => 'value']
])

<form method="POST" action="{{ $action }}" class="d-inline mb-0" onclick="event.stopPropagation();">
    @csrf
    @foreach($hidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" 
               {{ $checked ? 'checked' : '' }} 
               onchange="this.form.submit()"
               title="{{ $title }}">
    </div>
</form>

