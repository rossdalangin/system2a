<div class="wrap">
	<h1><?php _e( 'Smart Content Calendar', 'agency-nexus' ); ?></h1>

	<div style="background: #fff; border-left: 4px solid #2196f3; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
		<h3><?php _e( 'How to Manage Your Content Strategy', 'agency-nexus' ); ?></h3>
		<p><?php _e( 'Use this calendar to visualize your agency\'s content output across all clients. This helps you spot gaps in your strategy and ensures a consistent posting schedule.', 'agency-nexus' ); ?></p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong>Draft & Store:</strong> Create content items using the form below. They will appear in the "Unscheduled" section.</li>
			<li><strong>Schedule:</strong> Drag items from the "Unscheduled" section or between dates to set their publishing schedule.</li>
			<li><strong>Edit & Approve:</strong> Click "edit" on any item to refine the copy or send it to the client for approval via <strong>ApprovalFlow</strong>.</li>
		</ul>
	</div>

	<p><a href="<?php echo admin_url('admin.php?page=an-content-list'); ?>" class="button"><?php _e('View Content List for Editing', 'agency-nexus'); ?></a></p>

	<?php wp_nonce_field( 'an_calendar_nonce', 'security' ); ?>

	<div id="an-content-creation" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
		<h3>Create New Content</h3>
		<form id="an-create-content-form" style="display: flex; gap: 10px; align-items: flex-end;">
			<div>
				<label>Project</label><br>
				<select name="project_id" required>
					<?php foreach ( $projects as $project ) : ?>
						<option value="<?php echo $project->id; ?>"><?php echo esc_html( $project->title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label>Title</label><br>
				<input type="text" name="title" required>
			</div>
			<div>
				<label>Content Snippet</label><br>
				<input type="text" name="content">
			</div>
			<div>
				<label>Media</label><br>
				<input type="hidden" name="media_url" id="content_media_url">
				<button type="button" id="upload_content_media_btn" class="button">Attach Media</button>
				<span id="media_preview_name" style="font-size: 11px; color: #666;"></span>
			</div>
			<button type="submit" class="button button-primary">Add to Unscheduled</button>
		</form>
	</div>

	<div id="an-calendar-container" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; margin-top: 20px;">
		<?php
		$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
		foreach ($days as $day) : ?>
			<div class="calendar-day-header" style="font-weight: bold; text-align: center; background: #eee; padding: 10px; border: 1px solid #ddd;">
				<?php echo $day; ?>
			</div>
		<?php endforeach; ?>

		<?php
		// Calculate current week's dates starting from Monday
		$monday = strtotime('monday this week');
		for ($i = 0; $i < 7; $i++) :
			$current_date = date('Y-m-d', strtotime("+$i days", $monday));
			$display_day = date('j M', strtotime($current_date));
			?>
			<div class="calendar-day" data-date="<?php echo $current_date; ?>" style="min-height: 150px; background: #fff; border: 1px solid #ddd; padding: 10px;" ondrop="drop(event)" ondragover="allowDrop(event)">
				<div class="day-number" style="color: #999; margin-bottom: 5px;"><?php echo $display_day; ?></div>
				<?php
				foreach ($content_items as $item) {
					if (substr((string)$item->scheduled_date, 0, 10) === $current_date) {
						?>
						<div class="content-item" draggable="true" ondragstart="drag(event)" id="content-<?php echo $item->id; ?>" data-id="<?php echo $item->id; ?>" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 5px; margin-bottom: 5px; cursor: move; font-size: 12px; position: relative;">
							<?php echo esc_html($item->title); ?>
							<span class="delete-item" onclick="deleteContent(<?php echo $item->id; ?>)" style="position: absolute; right: 2px; top: 2px; cursor: pointer; color: red; font-weight: bold;">×</span>
							<a href="?page=an-content-list&action=edit&id=<?php echo $item->id; ?>" style="font-size: 10px; text-decoration: none; color: #999; display: block;"><?php _e('edit', 'agency-nexus'); ?></a>
						</div>
						<?php
					}
				}
				?>
			</div>
		<?php endfor; ?>
	</div>

	<div id="unscheduled-content" style="margin-top: 30px; background: #f9f9f9; padding: 20px; border: 1px dashed #ccc;">
		<h3><?php _e( 'Unscheduled Content', 'agency-nexus' ); ?></h3>
		<div class="calendar-day" data-date="" style="min-height: 50px; display: flex; flex-wrap: wrap; gap: 10px;" ondrop="drop(event)" ondragover="allowDrop(event)">
			<?php
			foreach ($content_items as $item) {
				if (empty($item->scheduled_date)) {
					?>
					<div class="content-item" draggable="true" ondragstart="drag(event)" id="content-<?php echo $item->id; ?>" data-id="<?php echo $item->id; ?>" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 5px; cursor: move; font-size: 12px; position: relative;">
						<?php echo esc_html($item->title); ?>
						<span class="delete-item" onclick="deleteContent(<?php echo $item->id; ?>)" style="position: absolute; right: 2px; top: 2px; cursor: pointer; color: red; font-weight: bold;">×</span>
						<a href="?page=an-content-list&action=edit&id=<?php echo $item->id; ?>" style="font-size: 10px; text-decoration: none; color: #999; display: block;"><?php _e('edit', 'agency-nexus'); ?></a>
					</div>
					<?php
				}
			}
			?>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#upload_content_media_btn').on('click', function(e) {
		e.preventDefault();
		var frame = wp.media({
			title: 'Select Content Media',
			multiple: false
		}).open()
		.on('select', function(e){
			var attachment = frame.state().get('selection').first().toJSON();
			$('#content_media_url').val(attachment.url);
			$('#media_preview_name').text(attachment.filename);
		});
	});

	$('#an-create-content-form').on('submit', function(e) {
		e.preventDefault();
		const data = $(this).serialize() + '&action=an_create_content&security=' + $('#security').val();
		$.post(ajaxurl, data, function(response) {
			if (response.success) {
				const newItem = $('<div class="content-item" draggable="true" ondragstart="drag(event)" id="content-' + response.data.id + '" data-id="' + response.data.id + '" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 5px; cursor: move; font-size: 12px; position: relative;">' + response.data.title + '<span class="delete-item" onclick="deleteContent(' + response.data.id + ')" style="position: absolute; right: 2px; top: 2px; cursor: pointer; color: red; font-weight: bold;">×</span><a href="?page=an-content-list&action=edit&id=' + response.data.id + '" style="font-size: 10px; text-decoration: none; color: #999; display: block;">edit</a></div>');
				$('#unscheduled-content .calendar-day').append(newItem);
				$('#an-create-content-form')[0].reset();
				$('#media_preview_name').text('');
			}
		});
	});
});

function deleteContent(id) {
	if (!confirm('Delete this content?')) return;
	var jQuery = window.jQuery;
	var data = {
		action: 'an_delete_content',
		item_id: id,
		security: jQuery('#security').val()
	};
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			jQuery('#content-' + id).fadeOut();
		}
	});
}

function allowDrop(ev) {
	ev.preventDefault();
}

function drag(ev) {
	ev.dataTransfer.setData("text", ev.target.id);
}

function drop(ev) {
	ev.preventDefault();
	var data = ev.dataTransfer.getData("text");
	var target = ev.target;

	// Ensure we drop on a calendar-day div
	while (target && !target.classList.contains('calendar-day')) {
		target = target.parentElement;
	}

	if (target) {
		target.appendChild(document.getElementById(data));
		var itemId = document.getElementById(data).getAttribute('data-id');
		var newDate = target.getAttribute('data-date');

		updateContentDate(itemId, newDate);
	}
}

function updateContentDate(itemId, newDate) {
	var jQuery = window.jQuery;
	var data = {
		action: 'an_update_content_date',
		item_id: itemId,
		new_date: newDate,
		security: jQuery('#security').val()
	};

	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			console.log('Date updated successfully');
		} else {
			alert('Failed to update date');
		}
	});
}
</script>
<style>
.content-item:hover {
	background: #bbdefb !important;
}
</style>
