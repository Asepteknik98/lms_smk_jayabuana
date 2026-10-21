<?php
/** Kelompokkan kartu tampilan saja; data dan riwayat kelas asal tetap utuh. */
function kbm_pkl_dashboard_cards(array $cards,array $groups): array {
    $byId=[];foreach($cards as $card)$byId[(int)$card['class']['id']]=$card;
    $result=[];$used=[];
    foreach($groups as $group){
        $members=[];
        foreach($group['kelas_ids'] as $id)if(isset($byId[$id])){$members[]=$byId[$id];$used[$id]=true;}
        if(!$members)continue;
        // Kondisi yang perlu perhatian didahulukan, agar tidak tertutup status hijau.
        $priority=['merah'=>4,'kuning'=>3,'hijau'=>2,'abu'=>1];$representative=$members[0];
        foreach($members as $member)if(($priority[$member['state']['primary']['color']]??0)>($priority[$representative['state']['primary']['color']]??0))$representative=$member;
        $card=$representative;
        $card['class']=['id'=>'pkl:'.$group['id'],'nama_kelas'=>$group['nama']];
        $card['pkl_members']=$members;
        $card['context']=['sessions'=>[],'incomplete'=>false,'issues'=>[]];
        $card['meetings']=[];$card['state']['warnings']=[];$teachers=[];$statusCodes=[];$allConfigured=true;
        foreach($members as $member){
            $name=$member['class']['nama_kelas'];$context=$member['context'];
            if(count($context['sessions'])!==1)$allConfigured=false;
            $card['context']['sessions']=array_merge($card['context']['sessions'],$context['sessions']);
            $card['context']['incomplete']=$card['context']['incomplete']||$context['incomplete'];
            foreach($context['issues'] as $issue)$card['context']['issues'][]=$name.': '.$issue;
            foreach($member['meetings'] as $meeting){$meeting['pkl_kelas_asal']=$name;$card['meetings'][]=$meeting;}
            $card['state']['warnings']=array_merge($card['state']['warnings'],$member['state']['warnings']);
            $teacher=$member['state']['meeting']['nama_guru']??null;if($teacher)$teachers[]=$teacher;
            $statusCodes[]=$member['state']['primary']['code'];
        }
        $card['context']['sessions']=$allConfigured?array_values(array_unique($card['context']['sessions'])):[];
        if(count(array_unique($statusCodes))>1)$card['state']['primary']['label']='Status beragam · '.$representative['state']['primary']['label'];
        if($card['state']['meeting'])$card['state']['meeting']['nama_guru']=implode(' / ',array_unique($teachers));
        $result[]=$card;
    }
    foreach($cards as $card)if(!isset($used[(int)$card['class']['id']]))$result[]=$card;
    return $result;
}
function kbm_pkl_render_members(array $members): void {
    echo '<div class="small mt-2 kbm-pkl-members"><strong>Kelas asal:</strong>';
    foreach($members as $member){
        echo '<div class="border-top mt-2 pt-2"><strong>'.kbm_board_h($member['class']['nama_kelas']).'</strong><br>'.kbm_board_h($member['state']['primary']['label']);
        if($member['state']['meeting'])echo '<br>'.kbm_board_h($member['state']['meeting']['nama_guru']);
        echo '</div>';
    }
    echo '</div>';
}
