import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const sidebar = readFileSync(new URL('../../resources/views/layouts/sidebar.blade.php', import.meta.url), 'utf8');
const method = sidebar.match(/isActive\(path\) \{([\s\S]*?)\n        \}/)[1].replaceAll('&amp;', '&');
const matches = (location, path) => new Function('window', 'path', method)({ location: new URL(location) }, path);

test('only the selected income category is active, including when other filters are present', () => {
    for (const category of ['komite', 'bos', 'hibah', 'lainnya']) {
        const location = `https://eduja.test/spp/transaksi?category=${category}&search=murid`;
        for (const candidate of ['komite', 'bos', 'hibah', 'lainnya']) {
            assert.equal(matches(location, `/spp/transaksi?category=${candidate}`), candidate === category);
        }
    }
    assert.equal(matches('https://eduja.test/spp/transaksi', '/spp/transaksi?category=bos'), false);
});

test('ordinary menu paths still match with filters and trailing slashes', () => {
    assert.equal(matches('https://eduja.test/finance/accounts/?search=kas', '/finance/accounts'), true);
    assert.equal(matches('https://eduja.test/finance/accounts', '/finance/billing'), false);
    assert.equal(matches('https://eduja.test/finance/accounts', ''), false);
});
