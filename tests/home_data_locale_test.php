<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/medical_home_data.php';
class HomeLocaleStatement extends PDOStatement
{
    private array $rows=[];
    public function __construct(private string $sql, private array $fixtures) {}
    public function execute(?array $params=null): bool
    {
        if (!str_contains($this->sql, "status = 'published'") || !str_contains($this->sql, 'language_code = :locale')) {
            throw new RuntimeException('Every homepage query must filter published records by language');
        }
        $this->rows=array_values(array_filter($this->fixtures,static fn($row)=>$row['language_code']===$params[':locale'] && $row['status']==='published'));
        return true;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column=0): mixed { return count($this->rows); }
}
class HomeLocalePDO extends PDO
{
    public array $queries = [];
    public function __construct(private array $facilities, private array $toplists) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $this->queries[] = $query;
        return new HomeLocaleStatement($query,str_contains($query,'FROM medical_toplists t')?$this->toplists:$this->facilities);
    }
}
$records=[['slug'=>'viet','language_code'=>'vi','status'=>'published','has_content'=>true],['slug'=>'english','language_code'=>'en','status'=>'published','has_content'=>true],['slug'=>'hidden','language_code'=>'en','status'=>'draft','has_content'=>true]];
$snapshot = static fn(array $rows): array => ['facilities' => array_values(array_filter($rows,
    static fn(array $row): bool => $row['status'] === 'published')), 'doctors' => [], 'toplists' => [], 'cities' => []];
$checks=0;
$assert=static function(bool $ok) use (&$checks): void { if(!$ok)throw new RuntimeException('Locale isolation failed'); $checks++; };
foreach(['vi'=>'viet','en'=>'english'] as $locale=>$slug){
    $data=medical_home_data(new HomeLocalePDO($records,$records),$locale,$snapshot($records));
    $assert(array_column($data['facilities'],'slug')===[$slug]);
    $assert(array_column($data['toplists'],'slug')===[$slug]);
    $assert($data['stats']['facilities']===1);
}
$empty=medical_home_data(new HomeLocalePDO([$records[0]],[$records[0]]),'en',$snapshot([$records[0]]));
$assert($empty['facilities']===[] && $empty['toplists']===[] && $empty['stats']['facilities']===0);
$source=file_get_contents(dirname(__DIR__).'/Tem/home.php');
$assert(str_contains($source,'medical_home_data($pdo, $locale)') && str_contains($source,'Toplists in English will appear here soon.'));
$sourceRecord = array_replace($records[0], ['rating' => 5, 'reviews_count' => 3,
    'stored_rating' => '4.1', 'stored_reviews_count' => '3',
    'aggregate_ratings_json' => '[{"source":"Google Maps","score":4.8,"count":1000}]']);
$pdo = new HomeLocalePDO([$sourceRecord], []);
$canonical = medical_home_data($pdo, 'vi', $snapshot([$sourceRecord]))['facilities'][0];
$assert($canonical['rating'] === 4.1 && $canonical['reviews_count'] === 3);
$assert(count($pdo->queries) === 2 && str_contains($pdo->queries[0], 'LEFT JOIN (')
    && str_contains($pdo->queries[0], 'AS stored_rating') && str_contains($pdo->queries[0], 'AS stored_reviews_count')
    && str_contains($pdo->queries[0], 'ORDER BY stored_rating DESC, stored_reviews_count DESC'));
$withoutStored = $sourceRecord;
unset($withoutStored['stored_rating'], $withoutStored['stored_reviews_count']);
$emptyRating = medical_home_data(new HomeLocalePDO([$withoutStored], []), 'vi', $snapshot([$withoutStored]))['facilities'][0];
$assert($emptyRating['rating'] === 0.0 && $emptyRating['reviews_count'] === 0);
$publicSnapshot = $snapshot([$sourceRecord, $records[1], array_replace($sourceRecord, ['slug' => 'seed', 'has_content' => false])]);
$assert(medical_home_data(new HomeLocalePDO($records, $records), 'vi', $publicSnapshot)['stats']['facilities'] === 1);
echo "Homepage language data: {$checks} checks passed. Mocked DB only.\n";
