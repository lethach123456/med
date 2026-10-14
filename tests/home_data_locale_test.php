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
    public function __construct(private array $facilities, private array $toplists) {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        return new HomeLocaleStatement($query,str_contains($query,'FROM medical_toplists t')?$this->toplists:$this->facilities);
    }
}
$records=[['slug'=>'viet','language_code'=>'vi','status'=>'published'],['slug'=>'english','language_code'=>'en','status'=>'published'],['slug'=>'hidden','language_code'=>'en','status'=>'draft']];
$checks=0;
$assert=static function(bool $ok) use (&$checks): void { if(!$ok)throw new RuntimeException('Locale isolation failed'); $checks++; };
foreach(['vi'=>'viet','en'=>'english'] as $locale=>$slug){
    $data=medical_home_data(new HomeLocalePDO($records,$records),$locale);
    $assert(array_column($data['facilities'],'slug')===[$slug]);
    $assert(array_column($data['toplists'],'slug')===[$slug]);
    $assert($data['stats']['facilities']===1);
}
$empty=medical_home_data(new HomeLocalePDO([$records[0]],[$records[0]]),'en');
$assert($empty['facilities']===[] && $empty['toplists']===[] && $empty['stats']['facilities']===0);
$source=file_get_contents(dirname(__DIR__).'/Tem/home.php');
$assert(str_contains($source,'medical_home_data($pdo, $locale)') && str_contains($source,'Toplists in English will appear here soon.'));
echo "Homepage language data: {$checks} checks passed. Mocked DB only.\n";
