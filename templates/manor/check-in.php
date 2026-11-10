<?php include __DIR__.'/start.php';?><section class="narrow"><h1><?=me(__('manor.guest_check_in'))?></h1><h2><?=me($guest['name'])?></h2><p><?=me($guest['request']['unit_name'])?> · <?=me($guest['rsvp'])?></p>
<?php if($result):?><p class="panel" role="status"><?=me(__($result['duplicate']?'manor.duplicate_scan':'manor.check_in_recorded'))?> <?=me($result['checked_at']??'')?></p><?php endif?>
<form method="post"><?=csrf()?><button><?=me(__('manor.confirm_check_in'))?></button></form>
<p><a href="/admin/manor/guests?request_id=<?=me($guest['request_id'])?>"><?=me(__('manor.guest_list'))?></a></p></section><?php include __DIR__.'/end.php';?>
