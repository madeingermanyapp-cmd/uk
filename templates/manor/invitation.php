<?php include __DIR__.'/start.php';$design=json_decode($guest['template_data'],true);$event=json_decode($guest['data'],true);$arabic=\App\Engine\Locale::getLocale()==='ar';$suffix=$arabic?'_ar':''; ?>
<section class="invitation invitation-<?=me($guest['style'])?>" style="--invite-accent:<?=me($design['accent'])?>;--invite-paper:<?=me($design['background'])?>">
<p class="eyebrow"><?=me($settings['website_name'])?></p>
<h1><?=me($design['heading'.$suffix]?:$design['heading'])?></h1>
<p class="guest-name"><?=me($guest['name'])?></p>
<h2><?=me($event['event_name'])?></h2>
<p><?=nl2br(me($design['message'.$suffix]?:$design['message']))?></p>
<p><?=me($event['host_name'])?> · <?=me($event['venue_label'])?></p>
<p><?=me(\App\Manor\Inventory::local($guest['request']['starts_at']))?> — <?=me(\App\Manor\Inventory::local($guest['request']['ends_at']))?> · Europe/London</p>
<p><?=me(__('manor.rsvp_status'))?>: <?=me(__('manor.rsvp_'.$guest['rsvp']))?></p>
<?php if($guest['request']['starts_at']>gmdate('Y-m-d H:i:s')):?><form method="post"><?=csrf()?><div class="actions"><button name="rsvp" value="accepted"><?=me(__('manor.rsvp_accept'))?></button><button name="rsvp" value="declined"><?=me(__('manor.rsvp_decline'))?></button></div></form><?php endif?>
<?php if($settings['flags']['qr_attendance']):?><img class="guest-qr" src="/manor/invitation/<?=me($token)?>/qr" alt="<?=me(__('manor.guest_qr'))?>"><p><?=me(__('manor.present_qr'))?></p><?php endif?>
<p><?=nl2br(me($design['footer'.$suffix]?:$design['footer']))?></p>
</section><?php include __DIR__.'/end.php';?>
