@props(['name', 'label', 'value' => '', 'type' => 'text', 'required' => false])
<div class="work-field">
    <label for="{{ $name }}">{{ $label }}{{ $required ? ' (wajib)' : '' }}</label>
    @if($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="{{ $name }}-error" {{ $attributes }}>{{ old($name, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required) aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" aria-describedby="{{ $name }}-error" {{ $attributes }}>
    @endif
    <span id="{{ $name }}-error" class="work-muted work-error">@error($name){{ $message }}@enderror</span>
</div>
