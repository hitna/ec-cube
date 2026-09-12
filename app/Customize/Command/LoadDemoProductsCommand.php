<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Customize\Command;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Block;
use Eccube\Entity\BlockPosition;
use Eccube\Entity\Category;
use Eccube\Entity\ClassCategory;
use Eccube\Entity\ClassName;
use Eccube\Entity\Layout;
use Eccube\Entity\Master\DeviceType;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Master\SaleType;
use Eccube\Entity\Member;
use Eccube\Entity\Product;
use Eccube\Entity\ProductCategory;
use Eccube\Entity\ProductClass;
use Eccube\Entity\ProductImage;
use Eccube\Entity\ProductStock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * デモ用の商品データを投入する.
 *
 * 商品名・説明・価格はすべて架空のもの。
 * 画像は html/user_data/assets/sample/ に置かれたものを
 * 600x600 の白背景に配置し直して html/upload/save_image/ に書き出す。
 *
 * 何度実行しても同じ状態になるよう、商品コードをキーに上書きする。
 */
class LoadDemoProductsCommand extends Command
{
    protected static $defaultName = 'customize:load-demo-products';

    private const CANVAS = 600;
    private const FIT = 468;
    private const MAX_SCALE = 1.6;

    /** @var EntityManagerInterface */
    private $entityManager;

    /** @var EccubeConfig */
    private $eccubeConfig;

    public function __construct(EntityManagerInterface $entityManager, EccubeConfig $eccubeConfig)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->eccubeConfig = $eccubeConfig;
    }

    protected function configure()
    {
        $this->setDescription('デモ用の商品・カテゴリデータを投入する（商品情報はすべて架空）');
    }

    /**
     * カテゴリ構成. id => [名称, 親id, 階層, 表示順]
     */
    private function categoryDefs(): array
    {
        return [
            2 => ['新着アイテム', null, 1, 1],
            1 => ['アウトドア', null, 1, 2],
            3 => ['キャンプ用品', 1, 2, 3],
            4 => ['スリーピングギア', 3, 3, 4],
            5 => ['ホーム＆キッチン', null, 1, 5],
            6 => ['収納・インテリア', 5, 2, 6],
            7 => ['シューズ＆ウェア', null, 1, 7],
            8 => ['家電・ガジェット', null, 1, 8],
            9 => ['バッグ・小物', 7, 2, 9],
        ];
    }

    /**
     * 規格マスタ. 既存の「フレーバー/サイズ」をシューズ向けに付け替える.
     */
    private function classDefs(): array
    {
        return [
            'names' => [1 => 'カラー', 2 => 'サイズ'],
            'categories' => [
                1 => 'ブラック', 2 => 'オリーブ', 3 => 'サンド',
                4 => '25.0cm', 5 => '26.0cm', 6 => '27.0cm',
            ],
        ];
    }

    /**
     * 商品定義. すべて架空の商品名・説明・価格.
     */
    private function productDefs(): array
    {
        $f = 'Amazon.co.jp _ Books, Apparel, Electronics, Groceries & more_files';
        $g = 'Amazon.co.jp _ Books, Apparel, Electronics, Groceries & more_files2';

        return [
            [
                'id' => 1,  // 既存商品を差し替え（規格あり）
                'code' => 'RDG-BOOT',
                'name' => 'リッジライン トレッキングブーツ GX',
                'list' => '防水メンブレン採用。岩場でも滑りにくいビブラム調アウトソール。',
                'detail' => "縦走から日帰りハイクまで対応するミッドカットのトレッキングブーツです。\n"
                    ."甲まわりを面で支える独自ラストにより、長時間の歩行でも足が疲れにくい設計にしました。\n"
                    ."防水透湿メンブレンを内蔵しているため、沢の渡渉や急な雨でも内部までは濡れません。\n"
                    .'アウトソールは濡れた岩の上でのグリップを重視したコンパウンドを採用しています。',
                'image' => "$f/71XQs3uYpDL._SX285_.jpg",
                'price01' => 19800, 'price02' => 16500,
                'stock' => 24,
                'categories' => [7, 1, 2],
                'discount' => null,
                'variants' => true,
            ],
            [
                'id' => 2,  // 既存商品を差し替え（規格なし・割引あり）
                'code' => 'NDE-SB700',
                'name' => 'ノルディエ 封筒型シュラフ NS-700',
                'list' => '快適使用温度5℃。洗濯機で丸洗いできる封筒型スリーピングバッグ。',
                'detail' => "オートキャンプや車中泊に使いやすい封筒型のシュラフです。\n"
                    ."中綿には復元性の高い中空繊維を使用し、収納袋から出してすぐにロフトが戻ります。\n"
                    ."ファスナーを全開にすると掛け布団としても使えるため、夏場は肌掛けとしても活躍します。\n"
                    .'ご家庭の洗濯機で丸洗いできるので、シーズン終わりの手入れも簡単です。',
                'image' => "$f/71Our1JpAdL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 9800, 'price02' => 8800,
                'stock' => 98,
                'categories' => [4, 3, 1, 2],
                'discount' => 15,
                'variants' => false,
            ],
            [
                'code' => 'NDE-MAT5',
                'name' => 'ノルディエ エアスリープマット 5cm',
                'list' => '厚さ5cm。足踏みポンプ内蔵で約90秒で膨らむインフレーターマット。',
                'detail' => "凹凸のある地面でも底付きしにくい、厚さ5cmのエアマットです。\n"
                    ."本体にフットポンプを内蔵しているため、別途ポンプを持ち歩く必要がありません。\n"
                    ."表面には細かなエンボス加工を施し、寝袋が滑り落ちにくくなっています。\n"
                    .'収納時は500mlペットボトルとほぼ同じサイズまで小さくまとまります。',
                'image' => "$f/714tGKrU+-L._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 7480, 'price02' => 6600,
                'stock' => 40,
                'categories' => [4, 3, 1],
                'discount' => null,
            ],
            [
                'code' => 'RDG-POLE2',
                'name' => 'リッジライン カーボントレッキングポール 2本組',
                'list' => '1本228gの軽量カーボン製。レバーロック式で高さ調整が素早く行えます。',
                'detail' => "カーボンシャフトを採用した、1本228gの軽量トレッキングポールです。\n"
                    ."手袋をしたままでも扱えるレバーロック式で、登り下りに合わせて素早く長さを変えられます。\n"
                    ."グリップは汗を吸いにくいEVA素材。ストラップは幅広で手首への負担を抑えます。\n"
                    .'ゴムキャップ・スノーバスケット・収納袋が付属します。',
                'image' => "$f/31ZKJ4PAjHL._SR250,250_.jpg",
                'price01' => 8800, 'price02' => 7480,
                'stock' => 60,
                'categories' => [3, 1],
                'discount' => null,
            ],
            [
                'code' => 'SLE-PS700',
                'name' => 'ソルエナ ポータブル電源 700Wh',
                'list' => 'リン酸鉄リチウム採用で約3000サイクル。AC出力700W／ソーラーパネル同梱。',
                'detail' => "キャンプから停電時の備えまで使える、容量700Whのポータブル電源です。\n"
                    ."長寿命のリン酸鉄リチウムイオン電池を採用し、約3000サイクルの充放電に対応します。\n"
                    ."AC・USB Type-C・シガーソケットを備え、ノートPCや小型冷蔵庫にも給電できます。\n"
                    .'同梱の100Wソーラーパネルを使えば、電源のない場所でも充電が可能です。',
                'image' => "$f/71aY8WtfofL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 99800, 'price02' => 89800,
                'stock' => 12,
                'categories' => [8, 1],
                'discount' => 10,
            ],
            [
                'code' => 'SLE-PB20K',
                'name' => 'ソルエナ モバイルバッテリー 20000mAh',
                'list' => 'ケーブル内蔵で手ぶら充電。最大65W出力でノートPCにも対応。',
                'detail' => "USB Type-Cケーブルを本体に内蔵した、20000mAhのモバイルバッテリーです。\n"
                    ."ケーブルを忘れてもスマートフォンをそのまま充電でき、外出先での困りごとを減らします。\n"
                    ."最大65WのPD出力に対応しているため、13インチクラスのノートPCにも給電できます。\n"
                    .'残量はデジタル表示で1%単位まで確認できます。',
                'image' => "$f/61xR7vy1-ML._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 5980, 'price02' => 4980,
                'stock' => 150,
                'categories' => [8, 2],
                'discount' => null,
            ],
            [
                'code' => 'ECF-SPK2',
                'name' => 'エコーフィールド ポータブルスピーカー S2',
                'list' => 'IPX7防水。最大14時間再生、2台つなげばステレオ再生にも対応。',
                'detail' => "水辺やお風呂場でも使える、IPX7防水のBluetoothスピーカーです。\n"
                    ."小型ながらパッシブラジエーターを備え、低音の量感を確保しました。\n"
                    ."満充電から最大14時間の連続再生が可能で、一日中つけっぱなしでも余裕があります。\n"
                    .'同じモデルを2台ペアリングすると、左右に分かれたステレオ再生ができます。',
                'image' => "$f/41Bo2Nn2tcL._SR250,250_.jpg",
                'price01' => 8800, 'price02' => 7200,
                'stock' => 85,
                'categories' => [8, 2],
                'discount' => null,
            ],
            [
                'code' => 'CRE-MP16',
                'name' => 'クオーレ ホーローミルクパン 16cm',
                'list' => '天然木ハンドルのホーロー鍋。IH・直火対応、そのまま食卓に出せます。',
                'detail' => "少量の調理にちょうどよい、直径16cmのホーローミルクパンです。\n"
                    ."酸や塩分に強いホーロー加工なので、トマトソースや煮物の作り置きにも向いています。\n"
                    ."ハンドルには天然木を使用し、熱が伝わりにくく持ちやすい形状にしました。\n"
                    .'IH・ガス・オーブンに対応。白い外観はそのまま食卓に出しても違和感がありません。',
                'image' => "$f/21hJheJVJBL._SR250,250_.jpg",
                'price01' => 4950, 'price02' => 4290,
                'stock' => 70,
                'categories' => [5],
                'discount' => null,
            ],
            [
                'code' => 'TMB-RACK4',
                'name' => 'ティンバーワークス オープンラック 4段',
                'list' => '天然木×スチールの4段ラック。転倒防止金具付きで壁面にも固定できます。',
                'detail' => "天然木の棚板とスチールフレームを組み合わせた、幅80cmの4段オープンラックです。\n"
                    ."背面がないため圧迫感が少なく、間仕切りとして部屋の中央に置くこともできます。\n"
                    ."棚板1枚あたり約15kgまで載せられるので、書籍や食器の収納にも使えます。\n"
                    .'転倒防止用の固定金具が付属します。工具はすべて同梱されています。',
                'image' => "$f/31RzxYc8wyL._SR250,250_.jpg",
                'price01' => 15400, 'price02' => 13200,
                'stock' => 18,
                'categories' => [6, 5],
                'discount' => 20,
            ],
            [
                'code' => 'MKR-GKT-S',
                'name' => 'モクリ 洗えるガーゼケット シングル',
                'list' => '6重ガーゼ。洗うほどにやわらかくなる、一年中使える綿100%のケット。',
                'detail' => "綿100%の生地を6枚重ねた、シングルサイズのガーゼケットです。\n"
                    ."層の間に空気を含むため、夏は汗を吸って涼しく、冬は毛布の内側に重ねて暖かく使えます。\n"
                    ."洗濯を重ねるごとに繊維がほぐれ、やわらかい肌ざわりに育っていきます。\n"
                    .'ご家庭の洗濯機で洗えます。乾きが早いので、部屋干しでも扱いやすい一枚です。',
                'image' => "$f/31om5McZsTL._SR250,250_.jpg",
                'price01' => 4400, 'price02' => 3850,
                'stock' => 120,
                'categories' => [5, 4],
                'discount' => null,
            ],
            [
                'code' => 'UMB-AUTO',
                'name' => 'アンブラ 自動開閉 折りたたみ傘',
                'list' => 'ワンタッチ自動開閉。10本骨で風に強く、重さ約295g。',
                'detail' => "ボタンひとつで開閉できる、10本骨の自動開閉折りたたみ傘です。\n"
                    ."骨の本数が多いぶん風を受け流しやすく、突風でも裏返りにくい構造にしています。\n"
                    ."生地には撥水加工を施しており、軽く振るだけで水滴が落ちます。\n"
                    .'重さは約295g。収納ケースと予備の石突きが付属します。',
                'image' => "$f/61V8+FOiOyL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 3480, 'price02' => 2980,
                'stock' => 200,
                'categories' => [5, 2],
                'discount' => null,
            ],
            [
                'code' => 'ARM-DIF200',
                'name' => 'ティンバーワークス 布製ストレージボックス L',
                'list' => '不織布＋厚紙芯。使わないときは畳んで厚さ3cmに収まります。',
                'detail' => "衣類や寝具をまとめてしまえる、幅60cmの布製ストレージボックスです。\n"
                    ."側面と底面に厚紙芯を入れているため、中身が少なくても形が崩れません。\n"
                    ."前面は透明窓付きで、開けなくても中に何が入っているか確認できます。\n"
                    .'使わない季節は畳んで厚さ3cmになるので、クローゼットの隙間にしまっておけます。',
                'image' => "$f/21u1BIYsdmL._SR250,250_.jpg",
                'price01' => 3960, 'price02' => 3300,
                'stock' => 90,
                'categories' => [6, 2],
                'discount' => null,
            ],
            [
                'code' => 'NDE-LTN01',
                'name' => 'ノルディエ LEDランタン アンバーグロー',
                'list' => '無段階調光。ゆらぎモード搭載で、灯油ランタンのような明かりに。',
                'detail' => "オイルランタンの雰囲気をLEDで再現した、充電式のランタンです。\n"
                    ."ダイヤルを回すと無段階で明るさが変わり、最小にすると常夜灯として使えます。\n"
                    ."炎のようにゆらめく「ゆらぎモード」を備えており、テント内の雰囲気づくりにも向いています。\n"
                    .'満充電から最大40時間点灯。吊り下げ用のハンドルは金属製で、ぐらつきません。',
                'image' => "$g/51S1snRG2UL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 5800, 'price02' => 4800,
                'stock' => 65,
                'categories' => [3, 1, 2],
                'discount' => null,
            ],
            [
                'code' => 'RDG-GLV01',
                'name' => 'リッジライン レザーワークグローブ',
                'list' => '牛革一枚仕立て。焚き火まわりの作業や薪割りに。',
                'detail' => "厚手の牛革を一枚で裁った、焚き火作業用のグローブです。\n"
                    ."縫製箇所を減らし、熱源に近づけても糸が傷みにくい構造にしました。\n"
                    ."使い込むほどに手の形に馴染み、色にも深みが出てきます。\n"
                    .'手首まで覆う長めの丈で、火の粉から腕を守ります。',
                'image' => "$g/61JE1OakAnL._SR210,210_.jpg",
                'price01' => 4400, 'price02' => 3500,
                'stock' => 110,
                'categories' => [3, 1],
                'discount' => null,
            ],
            [
                'code' => 'RDG-ANK01',
                'name' => 'リッジライン アノラックパーカー',
                'list' => '撥水加工のかぶりタイプ。大容量のカンガルーポケット付き。',
                'detail' => "頭からかぶって着る、クラシックな形のアノラックパーカーです。\n"
                    ."表地には撥水加工を施しており、小雨程度なら弾きます。\n"
                    ."前面のカンガルーポケットは地図やグローブがそのまま入る大きさです。\n"
                    .'裾のドローコードを絞れば、風の吹き込みを抑えられます。',
                'image' => "$g/6117xDOjbjL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 15400, 'price02' => 13000,
                'stock' => 32,
                'categories' => [7, 1, 2],
                'discount' => null,
            ],
            [
                'code' => 'RDG-MTP3L',
                'name' => 'リッジライン マウンテンパーカー 3レイヤー',
                'list' => '耐水圧20000mm／透湿15000g。全ての縫い目にシームテープ処理。',
                'detail' => "縦走や雪山でも使える、3レイヤー構造のマウンテンパーカーです。\n"
                    ."耐水圧20000mm・透湿15000g/m2の生地を採用し、雨と汗の両方に対応します。\n"
                    ."すべての縫い目にシームテープを貼っているため、縫い目からの浸水がありません。\n"
                    .'ヘルメットの上からかぶれるフードは、3方向のドローコードで顔まわりを調整できます。',
                'image' => "$g/61WpFFLdvvL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 26400, 'price02' => 22000,
                'stock' => 20,
                'categories' => [7, 1],
                'discount' => 15,
            ],
            [
                'code' => 'RDG-SSJ01',
                'name' => 'リッジライン ソフトシェルジャケット',
                'list' => '裏起毛の4WAYストレッチ。行動着として一年の大半で使えます。',
                'detail' => "ストレッチ性のある生地を使った、動きを妨げないソフトシェルジャケットです。\n"
                    ."裏地は起毛仕上げで、薄手ながら肌ざわりが良く保温性もあります。\n"
                    ."春秋は単体で、冬はレインシェルの下に重ねて一年の大半を通して使えます。\n"
                    .'両脇のポケットはファスナー付きで、中身が落ちません。',
                'image' => "$g/31+sQSY6jrL._SR210,210_.jpg",
                'price01' => 13200, 'price02' => 11000,
                'stock' => 45,
                'categories' => [7, 1],
                'discount' => null,
            ],
            [
                'code' => 'RDG-SNK01',
                'name' => 'リッジライン シティランスニーカー',
                'list' => '片足220gの軽量ニットアッパー。丸洗いできます。',
                'detail' => "通勤から軽いジョギングまで兼ねられる、片足220gのスニーカーです。\n"
                    ."アッパーは一体成型のニットで、縫い目による当たりがありません。\n"
                    ."ミッドソールには反発性のある樹脂を使い、長く歩いても脚が疲れにくくなっています。\n"
                    .'インソールを外してネットに入れれば、洗濯機で丸洗いできます。',
                'image' => "$g/718pRjkRAfL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 8800, 'price02' => 7300,
                'stock' => 78,
                'categories' => [7, 2],
                'discount' => null,
            ],
            [
                'code' => 'MKR-SWT01',
                'name' => 'モクリ 裏起毛スウェット',
                'list' => '肉厚350gの裏起毛。部屋着にも外出にも使えるゆったりシルエット。',
                'detail' => "350g/m2の肉厚な生地を使った、裏起毛のスウェットです。\n"
                    ."身幅にゆとりを持たせつつ肩線を落としすぎない設計で、だらしなく見えません。\n"
                    ."袖口と裾のリブはしっかりと編み立ててあり、洗濯を繰り返しても広がりにくくなっています。\n"
                    .'部屋着としても、そのまま買い物に出られる程度の見た目を意識しました。',
                'image' => "$g/21PvYsN3vcL._SR210,210_.jpg",
                'price01' => 6600, 'price02' => 5500,
                'stock' => 95,
                'categories' => [7],
                'discount' => null,
            ],
            [
                'code' => 'CRE-TOTE1',
                'name' => 'クオーレ レザートートバッグ',
                'list' => '日本製のステアレザー。A4とノートPCが縦に入ります。',
                'detail' => "国内のタンナーで仕上げたステアレザーを使った、大きめのトートバッグです。\n"
                    ."A4の書類と13インチのノートPCが縦向きのまま入る容量があります。\n"
                    ."内側には仕切りと小物ポケットを設け、中で荷物が泳がないようにしました。\n"
                    .'持ち手は肩に掛けられる長さで、金具を使わず革だけで仕立てています。',
                'image' => "$g/51KT5WZCkmL._AC_AIweblab1374086,T2_SF232.5,232.5_QL80_.jpg",
                'price01' => 19800, 'price02' => 17000,
                'stock' => 22,
                'categories' => [9, 7],
                'discount' => null,
            ],
            [
                'code' => 'CRE-BOS2W',
                'name' => 'クオーレ 2WAYボストンバッグ',
                'list' => '1〜2泊向け。ショルダーストラップ付きで肩掛けにもできます。',
                'detail' => "1〜2泊の出張や小旅行にちょうどよい容量のボストンバッグです。\n"
                    ."付属のストラップを使えば肩掛けにでき、荷物が重い日でも扱いやすくなります。\n"
                    ."底面には自立用の鋲を打ってあるため、床に置いても形が崩れません。\n"
                    .'背面にはキャリーケースのハンドルに通せるベルトを備えています。',
                'image' => "$g/21kw4+meG9L._SR210,210_.jpg",
                'price01' => 13200, 'price02' => 11000,
                'stock' => 36,
                'categories' => [9, 7, 2],
                'discount' => null,
            ],
            [
                'code' => 'CRE-APR01',
                'name' => 'クオーレ リネンエプロン',
                'list' => '洗いざらしのリネン100%。肩紐はクロスタイプで着脱が簡単。',
                'detail' => "リネン100%の生地を洗い加工した、やわらかい風合いのエプロンです。\n"
                    ."肩紐がクロスするタイプなので、頭からかぶるだけで着られます。\n"
                    ."リネンは水を吸って乾くのが早く、水仕事の多いキッチンに向いています。\n"
                    .'大きめのポケットを2つ配置し、タオルやスマートフォンを入れておけます。',
                'image' => "$g/51YlMqmtm6L._AC_AIweblab1374086,T2_SF217.5,435_QL80_.jpg",
                'price01' => 5500, 'price02' => 4500,
                'stock' => 88,
                'categories' => [5],
                'discount' => null,
            ],
            [
                'code' => 'CRE-SLID6',
                'name' => 'クオーレ シリコンフタ 6枚セット',
                'list' => '直径6〜22cmの6枚組。ラップの代わりに繰り返し使えます。',
                'detail' => "お皿やボウルにかぶせて使う、シリコン製のフタ6枚セットです。\n"
                    ."直径6cmから22cmまで揃っているので、小鉢から大きめのボウルまで対応できます。\n"
                    ."電子レンジと食洗機に対応。使い捨てのラップを減らせます。\n"
                    .'耐熱230℃・耐冷-40℃で、そのまま冷凍庫に入れることもできます。',
                'image' => "$g/61YTORy6fmL._AC_AIweblab1374086,T2_SF217.5,435_QL80_.jpg",
                'price01' => 2750, 'price02' => 2300,
                'stock' => 170,
                'categories' => [5, 2],
                'discount' => 10,
            ],
            [
                'code' => 'SLE-SW3',
                'name' => 'ソルエナ スマートウォッチ SW-3',
                'list' => '1.85インチ有機EL。睡眠・心拍・血中酸素を計測、最大14日間駆動。',
                'detail' => "1.85インチの有機ELディスプレイを備えたスマートウォッチです。\n"
                    ."睡眠・心拍・血中酸素飽和度を自動で記録し、専用アプリでまとめて確認できます。\n"
                    ."通常の使い方であれば1回の充電で最大14日間動作します。\n"
                    .'5気圧防水なので、手を洗うときや水泳の際も外す必要がありません。',
                'image' => "$g/41OTt+Y65AL._AC_SR240,240_.jpg",
                'price01' => 14300, 'price02' => 12000,
                'stock' => 54,
                'categories' => [8, 2],
                'discount' => 25,
            ],
            [
                'code' => 'SLE-RING1',
                'name' => 'ソルエナ スマートリング R1',
                'list' => 'わずか4g。着けていることを忘れる軽さで睡眠を記録します。',
                'detail' => "重さ4gのチタン製スマートリングです。\n"
                    ."腕時計型と違って睡眠中も違和感が少なく、寝返りの邪魔になりません。\n"
                    ."心拍と体表温度の変化から睡眠の深さを推定し、起床時にスコアを表示します。\n"
                    .'1回の充電で約7日間動作。ケースに戻すだけで充電が始まります。',
                'image' => "$g/614bWJIlsOL._SR210,210_.jpg",
                'price01' => 27500, 'price02' => 25000,
                'stock' => 15,
                'categories' => [8],
                'discount' => null,
            ],
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $em = $this->entityManager;

        $Member = $em->getRepository(Member::class)->find(1) ?: $em->getRepository(Member::class)->findOneBy([]);
        $Status = $em->find(ProductStatus::class, ProductStatus::DISPLAY_SHOW);
        $SaleType = $em->getRepository(SaleType::class)->find(1);

        $io->section('カテゴリ');
        $categories = $this->syncCategories($io);

        $io->section('規格マスタ');
        $this->syncClasses($io);

        $io->section('トップページのブロック');
        $this->syncTopPageBlocks($io);

        $io->section('商品');
        $now = new \DateTime();
        foreach ($this->productDefs() as $def) {
            $Product = null;
            if (isset($def['id'])) {
                $Product = $em->getRepository(Product::class)->find($def['id']);
            }
            if (!$Product) {
                $Product = $this->findProductByCode($def['code']);
            }
            $isNew = false;
            if (!$Product) {
                $Product = new Product();
                $Product->setCreator($Member)->setCreateDate($now);
                $em->persist($Product);
                $isNew = true;
            }

            $Product
                ->setName($def['name'])
                ->setStatus($Status)
                ->setDescriptionList($def['list'])
                ->setDescriptionDetail($def['detail'])
                ->setSearchWord(null)
                ->setFreeArea(null)
                ->setNote(null)
                ->setUpdateDate($now);
            if (method_exists($Product, 'setDiscountRate')) {
                $Product->setDiscountRate($def['discount']);
            }
            $em->flush();

            $this->syncImage($Product, $Member, $def, $io);
            $this->syncCategoryLinks($Product, $def['categories'], $categories);
            $this->syncClassesForProduct($Product, $Member, $SaleType, $def, $now);

            $em->flush();
            $io->writeln(sprintf('  %s #%d %s（%s円）', $isNew ? '作成' : '更新', $Product->getId(), $def['name'], number_format($def['price02'])));
        }

        $io->success('デモ用の商品データを投入しました。');

        return Command::SUCCESS;
    }

    /**
     * 商品コードから商品を探す. 規格ありの場合は "CODE-01" のような接尾辞が付く.
     */
    private function findProductByCode(string $code): ?Product
    {
        /** @var ProductClass[] $classes */
        $classes = $this->entityManager->createQueryBuilder()
            ->select('pc')->from(ProductClass::class, 'pc')
            ->where('pc.code = :code OR pc.code LIKE :prefix')
            ->setParameter('code', $code)
            ->setParameter('prefix', $code.'-%')
            ->setMaxResults(1)
            ->getQuery()->getResult();

        return $classes ? $classes[0]->getProduct() : null;
    }

    private function syncCategories(SymfonyStyle $io): array
    {
        $em = $this->entityManager;
        $repo = $em->getRepository(Category::class);
        $Member = $em->getRepository(Member::class)->find(1);
        $now = new \DateTime();
        $map = [];

        foreach ($this->categoryDefs() as $id => [$name, $parentId, $hierarchy, $sortNo]) {
            $Category = $repo->find($id);
            if (!$Category) {
                $Category = new Category();
                $Category->setCreator($Member)->setCreateDate($now);
                $em->persist($Category);
            }
            $Category->setName($name)->setHierarchy($hierarchy)->setSortNo($sortNo)->setUpdateDate($now);
            $map[$id] = $Category;
        }
        $em->flush();

        // 親子関係は全カテゴリを作ってから設定する
        foreach ($this->categoryDefs() as $id => [$name, $parentId, $hierarchy, $sortNo]) {
            $map[$id]->setParent($parentId === null ? null : $map[$parentId]);
        }
        $em->flush();
        $io->writeln(sprintf('  %d 件のカテゴリを設定しました。', count($map)));

        return $map;
    }

    /**
     * トップページに並べるブロックと、その表示順を設定する.
     *
     * お買い得品ブロックは本体に無いため、無ければ登録する.
     * 並び順は カテゴリ特集 → お買い得品 → 新着商品.
     */
    private function syncTopPageBlocks(SymfonyStyle $io): void
    {
        $em = $this->entityManager;
        $now = new \DateTime();

        $Deal = $em->getRepository(Block::class)->findOneBy(['file_name' => 'deal_products']);
        if (!$Deal) {
            $Deal = new Block();
            $Deal
                ->setName('お買い得品')
                ->setFileName('deal_products')
                ->setUseController(false)
                ->setDeletable(true)
                ->setDeviceType($em->getRepository(DeviceType::class)->find(DeviceType::DEVICE_TYPE_PC))
                ->setCreateDate($now)
                ->setUpdateDate($now);
            $em->persist($Deal);
            $em->flush();
            $io->writeln('  お買い得品ブロックを登録しました。');
        }

        /** @var Layout $Layout トップページ用レイアウト */
        $Layout = $em->getRepository(Layout::class)->find(Layout::DEFAULT_LAYOUT_TOP_PAGE);

        // section 7 = メイン下. 並べたい順にブロックのファイル名を指定する.
        $order = ['category', 'deal_products', 'new_item'];
        $row = 1;
        foreach ($order as $fileName) {
            $Block = $em->getRepository(Block::class)->findOneBy(['file_name' => $fileName]);
            if (!$Block) {
                continue;
            }
            $Position = $em->getRepository(BlockPosition::class)->findOneBy([
                'Layout' => $Layout, 'Block' => $Block, 'section' => 7,
            ]);
            if (!$Position) {
                $Position = new BlockPosition();
                $Position
                    ->setLayout($Layout)->setLayoutId($Layout->getId())
                    ->setBlock($Block)->setBlockId($Block->getId())
                    ->setSection(7);
                $em->persist($Position);
            }
            $Position->setBlockRow($row++);
        }
        $em->flush();
        $io->writeln('  カテゴリ特集 → お買い得品 → 新着商品 の順に並べました。');
    }

    private function syncClasses(SymfonyStyle $io): void
    {
        $em = $this->entityManager;
        $defs = $this->classDefs();
        foreach ($defs['names'] as $id => $name) {
            $ClassName = $em->getRepository(ClassName::class)->find($id);
            if ($ClassName) {
                $ClassName->setName($name);
            }
        }
        foreach ($defs['categories'] as $id => $name) {
            $ClassCategory = $em->getRepository(ClassCategory::class)->find($id);
            if ($ClassCategory) {
                $ClassCategory->setName($name);
            }
        }
        $em->flush();
        $io->writeln('  カラー／サイズに付け替えました。');
    }

    private function syncImage(Product $Product, ?Member $Member, array $def, SymfonyStyle $io): void
    {
        $em = $this->entityManager;
        $src = $this->eccubeConfig['eccube_html_dir'].'/user_data/assets/sample/'.$def['image'];
        if (!is_file($src)) {
            $io->warning('画像が見つかりません: '.$def['image']);

            return;
        }

        $fileName = strtolower($def['code']).'.jpg';
        $dest = $this->eccubeConfig['eccube_save_image_dir'].'/'.$fileName;
        $this->renderOnCanvas($src, $dest);

        foreach ($Product->getProductImage() as $Old) {
            $Product->removeProductImage($Old);
            $em->remove($Old);
        }
        $em->flush();

        $Image = new ProductImage();
        $Image->setCreator($Member)->setFileName($fileName)->setSortNo(1)
            ->setCreateDate(new \DateTime())->setProduct($Product);
        $em->persist($Image);
        $Product->addProductImage($Image);
    }

    /**
     * 元画像の白い余白を取り除いたうえで、白い正方形キャンバスの中央に配置する.
     *
     * 元画像にもともと余白が含まれており、そのまま貼ると余白が二重になって
     * 商品が小さく見えるため、先にトリミングしている.
     */
    private function renderOnCanvas(string $src, string $dest): void
    {
        $image = @imagecreatefromstring(file_get_contents($src));
        if (!$image) {
            return;
        }
        $image = $this->trimWhiteBorder($image);
        $sw = imagesx($image);
        $sh = imagesy($image);
        $scale = min(self::MAX_SCALE, self::FIT / max($sw, $sh));
        $dw = (int) round($sw * $scale);
        $dh = (int) round($sh * $scale);

        $canvas = imagecreatetruecolor(self::CANVAS, self::CANVAS);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled(
            $canvas, $image,
            (int) ((self::CANVAS - $dw) / 2), (int) ((self::CANVAS - $dh) / 2),
            0, 0, $dw, $dh, $sw, $sh
        );
        imagejpeg($canvas, $dest, 88);
        imagedestroy($canvas);
        imagedestroy($image);
    }

    /**
     * ほぼ白一色の外周を切り落とす. 背景が白でない画像は何も変わらない.
     *
     * @param \GdImage|resource $image
     *
     * @return \GdImage|resource
     */
    private function trimWhiteBorder($image)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $threshold = 246;
        $step = 2;      // 1px ずつ見る必要はないので間引く

        $isBlank = function (array $points) use ($image, $threshold) {
            foreach ($points as [$x, $y]) {
                $rgb = imagecolorat($image, $x, $y);
                if ((($rgb >> 16) & 0xFF) < $threshold || (($rgb >> 8) & 0xFF) < $threshold || ($rgb & 0xFF) < $threshold) {
                    return false;
                }
            }

            return true;
        };

        $top = 0;
        $bottom = $h - 1;
        $left = 0;
        $right = $w - 1;
        while ($top < $bottom && $isBlank($this->rowPoints($top, $w, $step))) {
            $top++;
        }
        while ($bottom > $top && $isBlank($this->rowPoints($bottom, $w, $step))) {
            $bottom--;
        }
        while ($left < $right && $isBlank($this->colPoints($left, $h, $step))) {
            $left++;
        }
        while ($right > $left && $isBlank($this->colPoints($right, $h, $step))) {
            $right--;
        }

        $nw = $right - $left + 1;
        $nh = $bottom - $top + 1;
        if ($nw < 20 || $nh < 20 || ($nw === $w && $nh === $h)) {
            return $image;
        }

        $cropped = imagecrop($image, ['x' => $left, 'y' => $top, 'width' => $nw, 'height' => $nh]);
        if (!$cropped) {
            return $image;
        }
        imagedestroy($image);

        return $cropped;
    }

    private function rowPoints(int $y, int $w, int $step): array
    {
        $points = [];
        for ($x = 0; $x < $w; $x += $step) {
            $points[] = [$x, $y];
        }

        return $points;
    }

    private function colPoints(int $x, int $h, int $step): array
    {
        $points = [];
        for ($y = 0; $y < $h; $y += $step) {
            $points[] = [$x, $y];
        }

        return $points;
    }

    private function syncCategoryLinks(Product $Product, array $categoryIds, array $categories): void
    {
        $em = $this->entityManager;
        foreach ($Product->getProductCategories() as $Old) {
            $Product->removeProductCategory($Old);
            $em->remove($Old);
        }
        $em->flush();

        foreach ($categoryIds as $cid) {
            if (!isset($categories[$cid])) {
                continue;
            }
            $Link = new ProductCategory();
            $Link->setProduct($Product)->setProductId($Product->getId())
                ->setCategory($categories[$cid])->setCategoryId($categories[$cid]->getId());
            $em->persist($Link);
            $Product->addProductCategory($Link);
        }
    }

    private function syncClassesForProduct(Product $Product, ?Member $Member, ?SaleType $SaleType, array $def, \DateTime $now): void
    {
        $em = $this->entityManager;
        $hasVariants = !empty($def['variants']);

        $i = 0;
        foreach ($Product->getProductClasses() as $ProductClass) {
            $isDefault = !$ProductClass->isVisible();
            $ProductClass
                ->setSaleType($SaleType)
                ->setPrice01((string) $def['price01'])
                ->setPrice02((string) $def['price02'])
                ->setStockUnlimited(false)
                ->setStock($def['stock'])
                ->setSaleLimit(null)
                ->setUpdateDate($now);

            if (!$isDefault) {
                $i++;
                // 規格なし商品はコードを固定する. 接尾辞を付けると再実行時に
                // 商品を見つけられなくなり、重複登録されてしまう.
                $ProductClass->setCode($hasVariants ? sprintf('%s-%02d', $def['code'], $i) : $def['code']);
            } else {
                $ProductClass->setCode(null);
            }

            $Stock = $ProductClass->getProductStock();
            if (!$Stock) {
                $Stock = new ProductStock();
                $Stock->setCreator($Member)->setCreateDate($now);
                $em->persist($Stock);
                $ProductClass->setProductStock($Stock);
                $Stock->setProductClass($ProductClass);
            }
            $Stock->setStock($def['stock'])->setUpdateDate($now);
        }

        // 規格を持たない商品で ProductClass がまだ無い場合は作る
        if (!$hasVariants && count($Product->getProductClasses()) === 0) {
            $Stock = new ProductStock();
            $Stock->setCreator($Member)->setCreateDate($now)->setUpdateDate($now)->setStock($def['stock']);
            $em->persist($Stock);

            $ProductClass = new ProductClass();
            $ProductClass
                ->setProduct($Product)
                ->setSaleType($SaleType)
                ->setCode($def['code'])
                ->setCreator($Member)
                ->setStock($def['stock'])
                ->setStockUnlimited(false)
                ->setPrice01((string) $def['price01'])
                ->setPrice02((string) $def['price02'])
                ->setVisible(true)
                ->setCreateDate($now)
                ->setUpdateDate($now)
                ->setProductStock($Stock);
            $em->persist($ProductClass);
            $em->flush();
            $Stock->setProductClass($ProductClass);
            $Stock->setProductClassId($ProductClass->getId());
            $Product->addProductClass($ProductClass);
        }
    }
}
