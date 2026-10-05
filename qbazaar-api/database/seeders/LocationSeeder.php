<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds Qatar's location taxonomy: municipalities (`city`) and their key
 * districts. Districts are stored as `district`; we keep the third enum
 * value (`area`) reserved for future neighbourhood subdivisions inside a
 * district without needing another migration.
 *
 * Every place carries an approximate centre point: search falls back to it
 * for ads without their own coordinates, so "near me" works out of the box.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        Location::query()->delete();
        Cache::forget('locations.qatar');

        foreach ($this->cities() as $cityOrder => $city) {
            /** @var Location $parent */
            $parent = Location::query()->create([
                'parent_id' => null,
                'slug' => $city['slug'],
                'name' => $city['name'],
                'type' => LocationType::CITY->value,
                'lat' => $city['lat'],
                'lng' => $city['lng'],
                'order' => $cityOrder,
            ]);

            foreach ($city['districts'] as $districtOrder => $district) {
                Location::query()->create([
                    'parent_id' => $parent->id,
                    'slug' => $district['slug'],
                    'name' => $district['name'],
                    'type' => LocationType::DISTRICT->value,
                    'lat' => $district['lat'],
                    'lng' => $district['lng'],
                    'order' => $districtOrder,
                ]);
            }
        }
    }

    /**
     * Fills in coordinates on a database seeded before they existed, without
     * touching rows (and the ads pointing at them) that are already there.
     */
    public function backfillCoordinates(): int
    {
        $updated = 0;

        foreach ($this->cities() as $city) {
            foreach ([$city, ...$city['districts']] as $place) {
                $updated += Location::query()
                    ->where('slug', $place['slug'])
                    ->whereNull('lat')
                    ->update(['lat' => $place['lat'], 'lng' => $place['lng']]);
            }
        }

        return $updated;
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: array{ar: string, en: string},
     *     lat: float,
     *     lng: float,
     *     districts: list<array{slug: string, name: array{ar: string, en: string}, lat: float, lng: float}>,
     * }>
     */
    private function cities(): array
    {
        return [
            [
                'slug' => 'doha',
                'lat' => 25.2854,
                'lng' => 51.5310,
                'name' => ['ar' => 'الدوحة', 'en' => 'Doha'],
                'districts' => [
                    ['slug' => 'west-bay', 'lat' => 25.3225, 'lng' => 51.5297, 'name' => ['ar' => 'الخليج الغربي', 'en' => 'West Bay']],
                    ['slug' => 'al-sadd', 'lat' => 25.2847, 'lng' => 51.4997, 'name' => ['ar' => 'السد', 'en' => 'Al Sadd']],
                    ['slug' => 'al-mansoura', 'lat' => 25.2738, 'lng' => 51.5318, 'name' => ['ar' => 'المنصورة', 'en' => 'Al Mansoura']],
                    ['slug' => 'najma', 'lat' => 25.2719, 'lng' => 51.5459, 'name' => ['ar' => 'نجمة', 'en' => 'Najma']],
                    ['slug' => 'old-doha', 'lat' => 25.2867, 'lng' => 51.5333, 'name' => ['ar' => 'الدوحة القديمة', 'en' => 'Old Doha']],
                    ['slug' => 'souq-waqif-area', 'lat' => 25.2873, 'lng' => 51.5330, 'name' => ['ar' => 'منطقة سوق واقف', 'en' => 'Souq Waqif Area']],
                    ['slug' => 'al-bidda', 'lat' => 25.2963, 'lng' => 51.5229, 'name' => ['ar' => 'البدع', 'en' => 'Al Bidda']],
                    ['slug' => 'msheireb', 'lat' => 25.2858, 'lng' => 51.5262, 'name' => ['ar' => 'مشيرب', 'en' => 'Msheireb']],
                    ['slug' => 'fereej-bin-mahmoud', 'lat' => 25.2789, 'lng' => 51.5130, 'name' => ['ar' => 'فريج بن محمود', 'en' => 'Fereej Bin Mahmoud']],
                    ['slug' => 'al-hitmi', 'lat' => 25.2780, 'lng' => 51.5440, 'name' => ['ar' => 'الهتمي', 'en' => 'Al Hitmi']],
                    ['slug' => 'the-pearl', 'lat' => 25.3690, 'lng' => 51.5510, 'name' => ['ar' => 'اللؤلؤة', 'en' => 'The Pearl']],
                ],
            ],
            [
                'slug' => 'al-rayyan',
                'lat' => 25.2919,
                'lng' => 51.4244,
                'name' => ['ar' => 'الريان', 'en' => 'Al Rayyan'],
                'districts' => [
                    ['slug' => 'old-al-rayyan', 'lat' => 25.2950, 'lng' => 51.4270, 'name' => ['ar' => 'الريان القديم', 'en' => 'Old Al Rayyan']],
                    ['slug' => 'al-gharafa', 'lat' => 25.3330, 'lng' => 51.4610, 'name' => ['ar' => 'الغرافة', 'en' => 'Al Gharafa']],
                    ['slug' => 'education-city', 'lat' => 25.3150, 'lng' => 51.4400, 'name' => ['ar' => 'المدينة التعليمية', 'en' => 'Education City']],
                    ['slug' => 'aspire-zone', 'lat' => 25.2640, 'lng' => 51.4430, 'name' => ['ar' => 'منطقة أسباير', 'en' => 'Aspire Zone']],
                    ['slug' => 'al-aziziya', 'lat' => 25.2490, 'lng' => 51.4640, 'name' => ['ar' => 'العزيزية', 'en' => 'Al Aziziya']],
                    ['slug' => 'muaither', 'lat' => 25.2860, 'lng' => 51.3980, 'name' => ['ar' => 'معيذر', 'en' => 'Muaither']],
                    ['slug' => 'al-waab', 'lat' => 25.2610, 'lng' => 51.4760, 'name' => ['ar' => 'الوعب', 'en' => 'Al Waab']],
                ],
            ],
            [
                'slug' => 'al-wakra',
                'lat' => 25.1715,
                'lng' => 51.6034,
                'name' => ['ar' => 'الوكرة', 'en' => 'Al Wakra'],
                'districts' => [
                    ['slug' => 'al-wakra-center', 'lat' => 25.1700, 'lng' => 51.6030, 'name' => ['ar' => 'مركز الوكرة', 'en' => 'Al Wakra Center']],
                    ['slug' => 'wakrah-beach-area', 'lat' => 25.1580, 'lng' => 51.6170, 'name' => ['ar' => 'منطقة شاطئ الوكرة', 'en' => 'Wakrah Beach Area']],
                    ['slug' => 'mesaieed', 'lat' => 24.9900, 'lng' => 51.5480, 'name' => ['ar' => 'مسيعيد', 'en' => 'Mesaieed']],
                ],
            ],
            [
                'slug' => 'lusail',
                'lat' => 25.4200,
                'lng' => 51.4900,
                'name' => ['ar' => 'لوسيل', 'en' => 'Lusail'],
                'districts' => [
                    ['slug' => 'lusail-marina', 'lat' => 25.3870, 'lng' => 51.5290, 'name' => ['ar' => 'مرسى لوسيل', 'en' => 'Lusail Marina']],
                    ['slug' => 'fox-hills', 'lat' => 25.4130, 'lng' => 51.4970, 'name' => ['ar' => 'فوكس هيلز', 'en' => 'Fox Hills']],
                    ['slug' => 'al-erkyah', 'lat' => 25.4290, 'lng' => 51.5090, 'name' => ['ar' => 'العركية', 'en' => 'Al Erkyah']],
                    ['slug' => 'energy-city', 'lat' => 25.4050, 'lng' => 51.5120, 'name' => ['ar' => 'مدينة الطاقة', 'en' => 'Energy City']],
                    ['slug' => 'entertainment-city', 'lat' => 25.4380, 'lng' => 51.5180, 'name' => ['ar' => 'مدينة الترفيه', 'en' => 'Entertainment City']],
                    ['slug' => 'waterfront-district', 'lat' => 25.3990, 'lng' => 51.5260, 'name' => ['ar' => 'الواجهة البحرية', 'en' => 'Waterfront District']],
                ],
            ],
            [
                'slug' => 'al-khor',
                'lat' => 25.6804,
                'lng' => 51.4969,
                'name' => ['ar' => 'الخور', 'en' => 'Al Khor'],
                'districts' => [
                    ['slug' => 'al-khor-center', 'lat' => 25.6830, 'lng' => 51.5050, 'name' => ['ar' => 'مركز الخور', 'en' => 'Al Khor Center']],
                    ['slug' => 'al-thakhira', 'lat' => 25.7330, 'lng' => 51.5450, 'name' => ['ar' => 'الذخيرة', 'en' => 'Al Thakhira']],
                ],
            ],
            [
                'slug' => 'al-daayen',
                'lat' => 25.5100,
                'lng' => 51.4500,
                'name' => ['ar' => 'الضعاين', 'en' => 'Al Daayen'],
                'districts' => [
                    ['slug' => 'umm-qarn', 'lat' => 25.5320, 'lng' => 51.3990, 'name' => ['ar' => 'أم قرن', 'en' => 'Umm Qarn']],
                    ['slug' => 'leabaib', 'lat' => 25.4300, 'lng' => 51.4580, 'name' => ['ar' => 'لعبيب', 'en' => 'Leabaib']],
                ],
            ],
            [
                'slug' => 'umm-salal',
                'lat' => 25.4100,
                'lng' => 51.4000,
                'name' => ['ar' => 'أم صلال', 'en' => 'Umm Salal'],
                'districts' => [
                    ['slug' => 'umm-salal-mohammed', 'lat' => 25.4170, 'lng' => 51.4040, 'name' => ['ar' => 'أم صلال محمد', 'en' => 'Umm Salal Mohammed']],
                    ['slug' => 'umm-salal-ali', 'lat' => 25.4720, 'lng' => 51.3970, 'name' => ['ar' => 'أم صلال علي', 'en' => 'Umm Salal Ali']],
                ],
            ],
            [
                'slug' => 'al-shamal',
                'lat' => 26.1290,
                'lng' => 51.2000,
                'name' => ['ar' => 'الشمال', 'en' => 'Al Shamal'],
                'districts' => [
                    ['slug' => 'madinat-al-shamal', 'lat' => 26.1290, 'lng' => 51.2000, 'name' => ['ar' => 'مدينة الشمال', 'en' => 'Madinat Al Shamal']],
                    ['slug' => 'ar-ruays', 'lat' => 26.1360, 'lng' => 51.2150, 'name' => ['ar' => 'الرويس', 'en' => "Ar Ru'ays"]],
                ],
            ],
            [
                'slug' => 'al-shahaniya',
                'lat' => 25.3700,
                'lng' => 51.2200,
                'name' => ['ar' => 'الشحانية', 'en' => 'Al Shahaniya'],
                'districts' => [
                    ['slug' => 'al-shahaniya-center', 'lat' => 25.3710, 'lng' => 51.2120, 'name' => ['ar' => 'مركز الشحانية', 'en' => 'Al Shahaniya Center']],
                    ['slug' => 'rawdat-rashed', 'lat' => 25.2380, 'lng' => 51.2040, 'name' => ['ar' => 'روضة راشد', 'en' => 'Rawdat Rashed']],
                ],
            ],
        ];
    }
}
