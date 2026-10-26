<form method="GET" action="{{ route('spp.transaksi.index') }}" class="flex flex-wrap items-end gap-3" x-data="{}">
    <input type="hidden" name="tab" value="pemasukan">
    <div class="work-field min-w-0 w-full sm:w-64"><label for="pemasukan-category">Kategori Pemasukan</label><select id="pemasukan-category" name="category" @change="$el.form.requestSubmit()"><option value="">Pilih kategori</option>@foreach(['komite' => 'Komite', 'bos' => 'BOS', 'hibah' => 'Hibah', 'lainnya' => 'Lainnya'] as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select></div>
    <button type="submit" class="work-btn">Pilih Kategori</button>
</form>
