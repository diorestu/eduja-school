export function dataTable(rows = [], columns = [], options = {}) {
    const collator = new Intl.Collator('id-ID', { numeric: true, sensitivity: 'base' });
    return {
        rows: rows.map((row, index) => ({ ...row, __tableKey: index })),
        columns,
        query: '',
        page: 1,
        perPage: 10,
        sortKey: '',
        sortDirection: 'asc',
        loading: Boolean(options.loading),
        error: options.error || '',

        get filteredRows() {
            const query = this.query.trim().toLocaleLowerCase('id-ID');
            return this.rows.filter(row => !query || this.columns.some(column =>
                column.searchable !== false && this.format(row[column.key], column).toLocaleLowerCase('id-ID').includes(query)
            ));
        },

        get sortedRows() {
            const column = this.columns.find(column => column.key === this.sortKey);
            if (!column) return this.filteredRows;
            const direction = this.sortDirection === 'asc' ? 1 : -1;
            return [...this.filteredRows].sort((a, b) => {
                const left = a[column.key];
                const right = b[column.key];
                if (left == null && right == null) return 0;
                if (left == null) return 1;
                if (right == null) return -1;
                const comparison = ['currency', 'number'].includes(column.type)
                    ? Number(left) - Number(right)
                    : collator.compare(this.format(left, column), this.format(right, column));
                return comparison * direction;
            });
        },

        get pageCount() {
            return Math.max(1, Math.ceil(this.filteredRows.length / this.perPage));
        },

        get currentPage() {
            return Math.min(this.page, this.pageCount);
        },

        get pageRows() {
            return this.sortedRows.slice((this.currentPage - 1) * this.perPage, this.currentPage * this.perPage);
        },

        get startRow() {
            return this.filteredRows.length ? (this.currentPage - 1) * this.perPage + 1 : 0;
        },

        get endRow() {
            return Math.min(this.currentPage * this.perPage, this.filteredRows.length);
        },

        search(value) {
            this.query = value;
            this.page = 1;
        },

        resize(value) {
            this.perPage = [10, 25, 50].includes(Number(value)) ? Number(value) : 10;
            this.page = 1;
        },

        goTo(page) {
            this.page = Math.max(1, Math.min(page, this.pageCount));
        },

        sort(key) {
            if (!this.columns.some(column => column.key === key && column.sortable !== false)) return;
            this.sortDirection = this.sortKey === key && this.sortDirection === 'asc' ? 'desc' : 'asc';
            this.sortKey = key;
            this.page = 1;
        },

        getInitials(value) {
            if (!value) return '';
            const clean = String(value).replace(/[^a-zA-Z0-9\s]/g, ' ').trim();
            const words = clean.split(/\s+/).filter(Boolean);
            if (!words.length) return '';
            if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
            return (words[0][0] + words[1][0]).toUpperCase();
        },

        format(value, column) {
            if (value == null || value === '') return '-';
            if (column.type === 'boolean') return value ? (column.trueLabel || 'Ya') : (column.falseLabel || 'Tidak');
            if (column.type === 'currency') {
                const num = Number(value);
                if (Number.isNaN(num)) return '-';
                const hasDecimals = num % 1 !== 0;
                const minFrac = column.minimumFractionDigits !== undefined ? column.minimumFractionDigits : (hasDecimals ? 2 : 0);
                const maxFrac = column.maximumFractionDigits !== undefined ? column.maximumFractionDigits : 2;
                return `Rp ${new Intl.NumberFormat('id-ID', { minimumFractionDigits: minFrac, maximumFractionDigits: maxFrac }).format(num)}`;
            }
            if (column.type === 'number') return new Intl.NumberFormat('id-ID').format(Number(value));
            return String(value);
        },

        exportData(filename = 'data-export.csv') {
            if (!this.filteredRows.length) return '';
            const exportCols = this.columns.filter(c => c.key !== 'actions' && c.key !== 'aksi' && c.exportable !== false);
            const headers = exportCols.map(c => `"${(c.label || c.key).replace(/"/g, '""')}"`).join(',');
            const rows = this.filteredRows.map(row =>
                exportCols.map(c => {
                    const val = this.format(row[c.key], c);
                    return `"${String(val).replace(/"/g, '""')}"`;
                }).join(',')
            );
            const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers, ...rows].join('\r\n');
            if (typeof window !== 'undefined' && typeof document !== 'undefined') {
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement('a');
                link.setAttribute('href', encodedUri);
                link.setAttribute('download', filename);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            return csvContent;
        },
    };
}
