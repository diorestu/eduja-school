@props(['label' => 'Simpan', 'busy' => 'Menyimpan…'])
<button type="submit" class="work-btn work-btn-primary" :disabled="saving" :aria-busy="saving.toString()" {{ $attributes }}>
    <span x-show="!saving">{{ $label }}</span><span x-show="saving" x-cloak role="status">{{ $busy }}</span>
</button>
