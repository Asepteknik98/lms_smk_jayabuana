<?php
require __DIR__.'/../includes/monitoring_kbm_pkl_cards.php';
function kbm_board_h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pkl_card_ok(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
function pkl_test_card(int $id,string $color,string $session='siang'): array {
    $status=['color'=>$color,'code'=>$color,'label'=>'Status '.$color,'icon'=>'circle','warning'=>null];
    return ['class'=>['id'=>$id,'nama_kelas'=>'Kelas '.$id],
        'context'=>['sessions'=>$session?[$session]:[],'incomplete'=>false,'issues'=>[]],
        'state'=>['primary'=>$status,'meeting'=>null,'warnings'=>[]],'meetings'=>[]];
}
$id=str_repeat('a',24);$groups=[['id'=>$id,'nama'=>'PKL <Gabungan>','kelas_ids'=>[1,2]]];
$cards=[pkl_test_card(1,'hijau'),pkl_test_card(2,'merah'),pkl_test_card(3,'kuning')];
$result=kbm_pkl_dashboard_cards($cards,$groups);
pkl_card_ok(count($result)===2,'Two member cards collapse into one');
pkl_card_ok($result[0]['class']['id']==='pkl:'.$id,'Stable group identity');
pkl_card_ok($result[0]['state']['primary']['color']==='merah','Red status remains visible');
pkl_card_ok(str_contains($result[0]['state']['primary']['label'],'Status beragam'),'Mixed statuses marked');
pkl_card_ok($result[0]['context']['sessions']===['siang'],'Common session retained');
pkl_card_ok($result[1]===$cards[2]&&$cards[0]['class']['id']===1,'Other classes and original data unchanged');
$cards[1]['context']['sessions']=['pagi'];
$mixed=kbm_pkl_dashboard_cards($cards,$groups)[0];
pkl_card_ok(count($mixed['context']['sessions'])===2,'Mixed session not assigned to one session');
$cards[1]['context']['sessions']=[];$cards[1]['context']['incomplete']=true;$cards[1]['context']['issues']=['Sesi belum diatur'];
$incomplete=kbm_pkl_dashboard_cards($cards,$groups)[0];
pkl_card_ok($incomplete['context']['sessions']===[]&&$incomplete['context']['incomplete'],'Missing session marked incomplete');
pkl_card_ok($incomplete['context']['issues']===['Kelas 2: Sesi belum diatur'],'Issue identifies source class');
pkl_card_ok(kbm_pkl_dashboard_cards($cards,[])===$cards,'No groups preserves existing display');
$groups=['siang'=>[$result[0]],'pagi'=>[],'belum_diatur'=>[]];$shown=1;
require_once __DIR__.'/../includes/monitoring_kbm_cards.php';
ob_start();kbm_dashboard_render_cards($groups,$shown);$html=ob_get_clean();
pkl_card_ok(substr_count($html,'<article ')===1,'Only one rendered group card');
pkl_card_ok(str_contains($html,'data-kbm-open="pkl:'.$id.'"'),'Group dialog uses stable key');
pkl_card_ok(str_contains($html,'PKL &lt;Gabungan&gt;')&&str_contains($html,'Kelas 1')&&str_contains($html,'Kelas 2'),'Group and member names rendered safely');
echo "PKL dashboard grouping checks passed\n";
