#!/usr/bin/env python3
"""Rebuild the boundary artefacts the statistics page reads.

Sources, both redistributions of MLIT National Land Numerical Information
administrative boundaries (行政区域), retrieved 2026-09-01:

  smartnews-smri/japan-topography  simplification s0001 and s0010, designated
                                   cities merged into a single unit
  geolonia/japanese-addresses      市区町村名ローマ字, joined on the JIS code

Credit required by the source: 国土数値情報（行政区域データ）国土交通省

Usage: python3 data/geo/build.py [output-root]
"""

import csv
import collections
import gzip
import io
import json
import pathlib
import re
import sys
import urllib.request

SMARTNEWS = 'https://raw.githubusercontent.com/smartnews-smri/japan-topography/main/data/municipality'
GEOLONIA = 'https://raw.githubusercontent.com/geolonia/japanese-addresses/master/data/latest.csv'

PREFECTURES = f'{SMARTNEWS}/topojson/s0001/prefectures.json'
MUNICIPALITIES = f'{SMARTNEWS}/topojson/s0001/N03-21_210101_designated_city.json'
SHAPES = f'{SMARTNEWS}/geojson/s0010/N03-21_210101_designated_city.json'

TAIL = {'SHI', 'KU', 'CHO', 'MACHI', 'MURA', 'SON', 'TO', 'FU', 'KEN'}
BY_HAND = {'13362': 'Toshima', '43506': 'Yunomae'}
PRECISION = 5


def fetch(url):
    with urllib.request.urlopen(url) as answer:
        return answer.read()


def prefecture_codes(root):
    source = (root / 'src/Service/PrefectureNamer.php').read_text(encoding='utf-8')
    block = source.split('KANJI = [', 1)[1].split('];', 1)[0]
    pairs = re.findall(r"'([^']+)' => '([A-Za-z]+)',", block)

    if len(pairs) != 47:
        raise SystemExit(f'PrefectureNamer holds {len(pairs)} prefectures, expected 47.')

    return {name: (f'{i:02d}', romanized) for i, (name, romanized) in enumerate(pairs, start=1)}


def readings():
    seen = {}

    for row in csv.DictReader(io.TextIOWrapper(io.BytesIO(fetch(GEOLONIA)), encoding='utf-8')):
        code = row['市区町村コード']

        if code and code not in seen:
            seen[code] = row['市区町村名ローマ字']

    return seen


def tidy(raw):
    parts = [part for part in raw.upper().split() if part]

    if 'GUN' in parts:
        parts = parts[parts.index('GUN') + 1:]

    while parts and parts[-1] in TAIL:
        parts.pop()

    return ' '.join(part.capitalize() for part in parts)


def romanize(codes):
    table = readings()
    cities = collections.defaultdict(list)

    for code, raw in table.items():
        cities[code[:4]].append(raw)

    resolved = {}

    for code in codes:
        if code in BY_HAND:
            resolved[code] = BY_HAND[code]
            continue

        if code in table:
            resolved[code] = tidy(table[code])
            continue

        for raw in cities.get(code[:4], []):
            parts = raw.upper().split()

            if 'SHI' in parts:
                resolved[code] = tidy(' '.join(parts[:parts.index('SHI') + 1]))
                break

    return resolved


def write(path, payload):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, separators=(',', ':'), ensure_ascii=False), encoding='utf-8')


def rings(geometry):
    parts = [geometry['coordinates']] if geometry['type'] == 'Polygon' else geometry['coordinates']

    return [
        [[round(x, PRECISION), round(y, PRECISION)] for x, y in ring]
        for polygon in parts
        for ring in polygon
    ]


def build(root, out):
    codes = prefecture_codes(root)

    prefectures = json.loads(fetch(PREFECTURES))

    for geometry in prefectures['objects']['prefectures']['geometries']:
        name = geometry['properties']['N03_001']
        code, romanized = codes[name]
        geometry['properties'] = {'code': code, 'name': name, 'romanized': romanized}

    write(out / 'public/geo/prefectures.topo.json', prefectures)

    municipalities = json.loads(fetch(MUNICIPALITIES))
    municipalities['objects']['municipalities'] = municipalities['objects'].pop(
        next(iter(municipalities['objects']))
    )

    kept = []

    for geometry in municipalities['objects']['municipalities']['geometries']:
        properties = geometry.get('properties') or {}
        code = properties.get('N03_007')

        if code is None or len(code) != 5:
            continue

        name = (properties.get('N03_004') or '').strip() or (properties.get('N03_003') or '').strip()
        geometry['properties'] = {'code': code, 'name': name}
        kept.append(geometry)

    municipalities['objects']['municipalities']['geometries'] = kept
    resolved = romanize([geometry['properties']['code'] for geometry in kept])

    for geometry in kept:
        romanized = resolved.get(geometry['properties']['code'])

        if romanized:
            geometry['properties']['romanized'] = romanized

    write(out / 'public/geo/municipalities.topo.json', municipalities)

    index = []

    for feature in json.loads(fetch(SHAPES))['features']:
        code = feature['properties'].get('N03_007')

        if code is None or len(code) != 5:
            continue

        shape = rings(feature['geometry'])
        xs = [x for ring in shape for x, _ in ring]
        ys = [y for ring in shape for _, y in ring]
        index.append({'code': code, 'bbox': [min(xs), min(ys), max(xs), max(ys)], 'rings': shape})

    target = out / 'data/geo/municipalities.json.gz'
    target.parent.mkdir(parents=True, exist_ok=True)

    with gzip.open(target, 'wt', encoding='utf-8', compresslevel=9) as handle:
        json.dump(index, handle, separators=(',', ':'))

    print(f'{len(prefectures["objects"]["prefectures"]["geometries"])} prefectures, '
          f'{len(kept)} municipalities, {len(index)} indexed, '
          f'{len(kept) - len(resolved)} without a reading')


if __name__ == '__main__':
    project = pathlib.Path(__file__).resolve().parents[2]
    build(project, pathlib.Path(sys.argv[1]) if len(sys.argv) > 1 else project)
