@props([
    'action' => null, // Route name or URL
    'method' => 'POST',
    'confirm' => null, // Confirmation message (optional)
    'icon' => null, // Bootstrap icon class
    'variant' => 'outline-danger', // Button variant
    'title' => null,
    'hidden' => [], // Array of hidden input fields ['name' => 'value']
])

<form method="{{ $method }}" action="{{ $action }}" class="d-inline mb-0" 
      @if($confirm)onsubmit="return confirm('{{ $confirm }}')"@endif
      onclick="event.stopPropagation();">
    @csrf
    @if($method !== 'GET' && $method !== 'POST')
        @method($method)
    @endif
    @foreach($hidden as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <button type="submit" class="btn btn-{{ $variant }} border-0" title="{{ $title }}">
        @if($icon)
            <i class="{{ $icon }}"></i>
        @endif
        {{ $slot }}
    </button>
</form>


