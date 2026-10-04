import test from 'node:test';
import assert from 'node:assert/strict';
import { dataTable } from '../../resources/js/components/data-table.js';

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'balance', label: 'Saldo', type: 'currency' },
    { key: 'active', label: 'Status', type: 'boolean', trueLabel: 'Aktif', falseLabel: 'Nonaktif' },
];
const rows = [
    { name: 'Kas Utama', balance: '1000.50', active: true },
    { name: 'Bank Sekolah', balance: '90.00', active: false },
    { name: 'Kas Kecil', balance: '200.00', active: true },
];

test('search uses displayed values and resets pagination', () => {
    const table = dataTable(rows, columns);
    table.page = 2;
    table.search('nonaktif');
    assert.equal(table.page, 1);
    assert.deepEqual(table.filteredRows.map(row => row.name), ['Bank Sekolah']);
    table.search('KAS');
    assert.deepEqual(table.filteredRows.map(row => row.name), ['Kas Utama', 'Kas Kecil']);
});

test('currency sorting is numeric in both directions without mutating source rows', () => {
    const table = dataTable(rows, columns);
    table.sort('balance');
    assert.deepEqual(table.sortedRows.map(row => row.balance), ['90.00', '200.00', '1000.50']);
    table.sort('balance');
    assert.deepEqual(table.sortedRows.map(row => row.balance), ['1000.50', '200.00', '90.00']);
    assert.equal(rows[0].name, 'Kas Utama');
});

test('pagination clamps navigation and resets when page size changes', () => {
    const table = dataTable(Array.from({ length: 26 }, (_, i) => ({ name: `Rekening ${i}` })), columns);
    table.goTo(3);
    assert.equal(table.pageRows.length, 6);
    assert.equal(table.startRow, 21);
    assert.equal(table.endRow, 26);
    table.goTo(99);
    assert.equal(table.page, 3);
    table.resize(25);
    assert.equal(table.page, 1);
    assert.equal(table.pageRows.length, 25);
});

test('empty results have finite page bounds and keep missing values out of currency output', () => {
    const table = dataTable(rows, columns);
    table.search('tidak ditemukan');
    assert.equal(table.pageCount, 1);
    assert.equal(table.startRow, 0);
    assert.equal(table.endRow, 0);
    assert.equal(table.format(null, columns[1]), '-');
    assert.equal(table.format(false, columns[2]), 'Nonaktif');
});

test('the same table accepts a different schema and preserves leading zeroes in identifiers', () => {
    const table = dataTable([{ code: '001234', label: 'Produk A' }], [{ key: 'code', label: 'Kode' }, { key: 'label', label: 'Produk', sortable: false }]);
    table.search('001234');
    assert.equal(table.pageRows[0].code, '001234');
    table.sort('label');
    assert.equal(table.sortKey, '');
});

test('getInitials extracts two letters from multi-word names and single names', () => {
    const table = dataTable([], []);
    assert.equal(table.getInitials('Adi Putra Rizkillah'), 'AP');
    assert.equal(table.getInitials('Dio Restu Saputra'), 'DR');
    assert.equal(table.getInitials('Ni Putu Sintya Bhakti Pratiwi'), 'NP');
    assert.equal(table.getInitials('Annisa Widyastuti'), 'AW');
    assert.equal(table.getInitials('BCA'), 'BC');
    assert.equal(table.getInitials(''), '');
});

test('exportData generates valid CSV string with filtered data', () => {
    const table = dataTable(rows, columns);
    const csv = table.exportData('test.csv');
    assert.ok(csv.includes('"Nama","Saldo","Status"'));
    assert.ok(csv.includes('"Kas Utama"'));
    assert.ok(csv.includes('"Bank Sekolah"'));
});

