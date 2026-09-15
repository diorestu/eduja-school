@if(session('success'))
    <div class="work-notice" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="work-notice work-error" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="work-notice work-error" role="alert" tabindex="-1">
        <strong>Periksa isian sebelum menyimpan.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
