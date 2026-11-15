@props(['name'])
@error($name)
    <p class="mt-1.5 mb-0 text-[13px] font-medium text-danger" id="{{ $name }}-error">{{ $message }}</p>
@enderror
