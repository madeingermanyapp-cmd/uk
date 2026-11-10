<?php
$slots=[];
foreach(['hero','heritage','why','why_wide','faq','contact','logo','favicon'] as $slot)$slots[$slot]=__('manor.media_slot_'.$slot);
foreach($units as $unit)$slots[$unit['id']]=$unit['name'];
$sectionSelect=static function(string $selected='hero')use($slots):void { ?>
<label><?=me(__('manor.media_section'))?><select name="section"><?php foreach($slots as $value=>$label):?><option value="<?=me($value)?>" <?=$value===$selected?'selected':''?>><?=me($label)?></option><?php endforeach?></select></label>
<?php };
?>
<p><?=me(__('manor.media_edit_help'))?></p>
<a class="outline-button" href="/admin/manor/preview" target="_blank" rel="noopener"><?=me(__('manor.preview_draft'))?></a>
<a class="outline-button" href="/admin/manor/settings/content"><?=me(__('manor.publish_content'))?></a>
<div class="panel"><form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="media_upload"><?php $sectionSelect();?><?=field('alt',__('manor.alt_text'))?><input type="file" name="media" accept=".jpg,.jpeg,.png,.webp,.mp4" required><button><?=me(__('manor.upload_draft_media'))?></button></form></div>
<details><summary><?=me(__('manor.external_video'))?></summary><form method="post"><?=csrf()?><input type="hidden" name="action" value="external_media"><?=field('url',__('manor.https_video_url'),'','url')?><?php $sectionSelect();?><?=field('alt',__('manor.alt_text'))?><?=field('rights',__('manor.media_rights'))?><label class="check"><input type="checkbox" name="approved" value="1"><?=me(__('manor.approve_media'))?></label><button><?=me(__('manor.add_external_video'))?></button></form></details>
<h2><?=me(__('manor.media_order'))?></h2>
<?php foreach($draft['data']['media'] as $i=>$entry):?><article class="panel media-placement">
<h3><?=me($slots[$entry['section']??'']??($entry['section']??''))?></h3>
<?php if(($entry['kind']??'image')==='image'):?><img class="admin-media-preview" src="<?=me($entry['url'])?>" alt="<?=me($entry['alt']??'')?>" loading="lazy"><?php endif?>
<p><?=me($entry['alt']??'')?></p>
<form method="post" enctype="multipart/form-data"><?=csrf()?><input type="hidden" name="action" value="media_replace"><input type="hidden" name="index" value="<?=$i?>"><input type="hidden" name="expected_url" value="<?=me($entry['url'])?>"><?=field('alt',__('manor.alt_text'),$entry['alt']??'')?><label><?=me(__('manor.replace_image'))?><input type="file" name="media" accept=".jpg,.jpeg,.png,.webp,.mp4" required></label><button><?=me(__('manor.replace_draft_media'))?></button></form>
<form method="post" class="actions"><?=csrf()?><input type="hidden" name="action" value="media_order"><input type="hidden" name="index" value="<?=$i?>"><button name="move" value="-1" <?=$i===0?'disabled':''?>><?=me(__('manor.move_up'))?></button><button name="move" value="1" <?=$i===count($draft['data']['media'])-1?'disabled':''?>><?=me(__('manor.move_down'))?></button><button name="action" value="media_entry_remove"><?=me(__('manor.remove_media'))?></button></form></article><?php endforeach?>
<h2><?=me(__('manor.media_library'))?></h2>
<?php foreach($media as $asset):?><article class="panel"><p><?=me($asset['original_name'].' · '.$asset['status'])?></p>
<?php $assetUrl='/manor/media/'.$asset['storage_name'];if(str_starts_with($asset['mime'],'image/')):?><img class="admin-media-preview" src="<?=me($assetUrl)?>" alt="<?=me($asset['alt'])?>" loading="lazy"><?php endif?>
<a href="<?=me($assetUrl)?>"><?=me(__('manor.preview_media'))?></a>
<form method="post"><?=csrf()?><input type="hidden" name="action" value="media_review"><input type="hidden" name="media_id" value="<?=me($asset['id'])?>"><?=field('alt',__('manor.alt_text'),$asset['alt'])?><label><?=me(__('manor.media_rights'))?><textarea name="rights"><?=me($asset['rights'])?></textarea></label><label class="check"><input type="checkbox" name="approved" value="1" <?=in_array($asset['status'],['approved','published'],true)?'checked':''?>><?=me(__('manor.approve_media'))?></label><button><?=me(__('manor.save_rights'))?></button></form>
<form method="post"><?=csrf()?><input type="hidden" name="action" value="media_assign"><input type="hidden" name="media_id" value="<?=me($asset['id'])?>"><?php $sectionSelect();?><button><?=me(__('manor.use_media_here'))?></button></form>
<form method="post"><?=csrf()?><input type="hidden" name="action" value="media_remove"><input type="hidden" name="media_id" value="<?=me($asset['id'])?>"><button><?=me(__('manor.remove_media'))?></button></form></article><?php endforeach?>
