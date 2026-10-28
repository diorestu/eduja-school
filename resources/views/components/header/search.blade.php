@php
    $searchMenus = collect(\App\Helpers\MenuHelper::getMainNavItems())->flatMap(function ($item) {
        return isset($item['subItems'])
            ? collect($item['subItems'])->map(fn ($child) => ['name' => $item['name'].' · '.$child['name'], 'path' => $child['path']])
            : [['name' => $item['name'], 'path' => $item['path']]];
    })->unique('path')->values();
@endphp
<div class="relative" x-data="{
    open: false, query: '', menus: @js($searchMenus),
    get results() { const term = this.query.trim().toLocaleLowerCase('id'); return term ? this.menus.filter(item => item.name.toLocaleLowerCase('id').includes(term)).slice(0, 8) : []; },
    show() { this.open = !this.open; if (this.open) this.$nextTick(() => this.$refs.input.focus()); },
    close() { this.open = false; this.$refs.trigger.focus(); }
}" @click.outside="open = false" @keydown.escape.stop.prevent="close()">
    <button x-ref="trigger" type="button" @click="show()" aria-label="Cari menu" aria-controls="header-menu-search" :aria-expanded="open.toString()"
        class="inline-flex h-11 w-11 items-center justify-center text-gray-600 hover:text-brand-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400 dark:text-gray-300 dark:hover:text-brand-400">
        <i class="bx bx-search text-xl" aria-hidden="true"></i>
    </button>
    <div id="header-menu-search" x-show="open" x-cloak class="absolute left-0 top-full z-50 mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-gray-200 bg-white p-3 shadow-lg xl:left-auto xl:right-0 dark:border-gray-700 dark:bg-gray-900">
        <form role="search" @submit.prevent="if (results.length) window.location.assign(results[0].path)">
            <label for="header-search-input" class="sr-only">Cari menu</label>
            <input x-ref="input" id="header-search-input" type="search" x-model="query" autocomplete="off" placeholder="Cari menu..."
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:text-white">
        </form>
        <p x-show="!query.trim()" class="mt-3 text-xs text-gray-500 dark:text-gray-400">Ketik nama menu yang ingin dibuka.</p>
        <p x-show="query.trim() && !results.length" class="mt-3 text-xs text-gray-500 dark:text-gray-400" role="status">Menu tidak ditemukan.</p>
        <ul class="mt-2 max-h-72 overflow-y-auto">
            <template x-for="item in results" :key="item.path"><li><a :href="item.path" x-text="item.name" class="flex min-h-11 items-center rounded-lg px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline focus-visible:outline-brand-400 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus-visible:bg-gray-800"></a></li></template>
        </ul>
    </div>
</div>
