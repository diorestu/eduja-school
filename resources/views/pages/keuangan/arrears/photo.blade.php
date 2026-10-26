@if($student->photo_path)
    <img src="{{ route('finance.arrears.photo', $student) }}" alt="Foto {{ $student->name }}" width="36" height="36" loading="lazy" class="h-9 w-9 shrink-0 rounded-full object-cover">
@else
    <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
@endif
