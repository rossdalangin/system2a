<div class="agency-nexus-wrap">
	<h1><?php _e( 'Unified Communication Hub', 'agency-nexus' ); ?></h1>

	<div style="background: #fff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
		<h3><?php _e( 'Centralize Your Client Conversations', 'agency-nexus' ); ?></h3>
		<p><?php _e( 'Move your client communication out of messy email threads and into this unified hub. All messages and files are securely linked to the client record.', 'agency-nexus' ); ?></p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong>Real-Time Chat:</strong> Select a client from the left to start a secure conversation thread.</li>
			<li><strong>Canned Responses:</strong> (Staff only) Use the dropdown to insert pre-written templates and speed up your workflow.</li>
			<li><strong>Shared Files:</strong> Switch to the "Shared Files" tab to securely exchange assets and deliverables.</li>
		</ul>
	</div>

	<div style="display: flex; height: 600px; border: 1px solid #ccd0d4; background: #fff; margin-top: 20px;">
		<div id="client-list" style="width: 250px; border-right: 1px solid #eee; overflow-y: auto;">
			<?php foreach ($clients as $client) : ?>
				<div class="client-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="selectClient(<?php echo $client->id; ?>, '<?php echo esc_js($client->name); ?>')">
					<strong><?php echo esc_html($client->name); ?></strong>
					<div style="font-size: 11px; color: #666;">Last active: 2h ago</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div id="chat-window" style="flex: 1; display: flex; flex-direction: column;">
			<div id="chat-header" style="padding: 15px; border-bottom: 1px solid #eee; background: #f9f9f9; display: flex; justify-content: space-between; align-items: center;">
				<h3 id="selected-client-name" style="margin: 0;"><?php _e( 'Select a client to start chatting', 'agency-nexus' ); ?></h3>
				<div id="chat-tabs" style="display: none;">
					<button class="button chat-tab-btn active" data-tab="messages">Messages</button>
					<button class="button chat-tab-btn" data-tab="files">Shared Files</button>
				</div>
			</div>

			<div id="chat-content-panes" style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
			<div id="chat-messages" class="chat-pane" style="flex: 1; padding: 20px; overflow-y: auto; background: #fdfdfd;">
				<!-- Messages will appear here -->
				<div class="system-msg" style="text-align: center; color: #999; margin: 20px 0;"><?php _e( 'End-to-end encrypted conversation', 'agency-nexus' ); ?></div>
			</div>

			</div>
			<div id="chat-files" class="chat-pane" style="flex: 1; padding: 20px; overflow-y: auto; background: #fdfdfd; display: none;">
				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
					<h4>Shared Files</h4>
					<button type="button" id="share-file-btn" class="button button-primary">Upload & Share</button>
				</div>
				<ul id="shared-files-list"></ul>
			</div>
			</div>

			<div id="chat-input" style="padding: 15px; border-top: 1px solid #eee; display: none;">
				<?php if ( $is_team ) : ?>
				<div style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
					<select id="canned-response-select" style="width: 200px;">
						<option value=""><?php _e( 'Insert Canned Response...', 'agency-nexus' ); ?></option>
						<?php foreach ($responses as $resp) : ?>
							<option value="<?php echo esc_attr($resp->content); ?>"><?php echo esc_html($resp->title); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="an_ai_suggest_msg" style="margin-bottom: 0;"><?php _e( '✨ AI Suggest Response', 'agency-nexus' ); ?></button>
						<span id="an_ai_msg_loading" style="display:none; color:#666; font-style:italic; margin-left:10px;"><?php _e( 'Thinking...', 'agency-nexus' ); ?></span>
				</div>
				<?php endif; ?>
				<form id="an-message-form">
					<?php wp_nonce_field( 'an_clientsync_nonce', 'security' ); ?>
					<input type="hidden" name="client_id" id="chat-client-id">
					<div style="display: flex; gap: 10px;">
						<textarea name="message" id="chat-message-text" style="flex: 1; height: 60px;" placeholder="<?php _e( 'Type your message...', 'agency-nexus' ); ?>" disabled></textarea>
						<button type="submit" class="button button-primary" id="send-msg-btn" disabled><?php _e( 'Send', 'agency-nexus' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
function selectClient(id, name) {
	var jQuery = window.jQuery;
	jQuery('#selected-client-name').text(name);
	jQuery('#chat-client-id').val(id);
	jQuery('#chat-message-text').prop('disabled', false).focus();
	jQuery('#send-msg-btn').prop('disabled', false);
	jQuery('#chat-tabs, #chat-input').show();

	loadMessages(id);
	loadFiles(id);
}

function loadMessages(id) {
	var jQuery = window.jQuery;
	var data = {
		action: 'an_get_messages',
		client_id: id,
		security: jQuery('#security').val()
	};

	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			var html = '';
			var currentUserId = <?php echo get_current_user_id(); ?>;
			response.data.forEach(function(msg) {
				var isSent = msg.sender_id == currentUserId;
				var align = isSent ? 'right' : 'left';
				var bg = isSent ? '#0073aa' : '#eee';
				var color = isSent ? '#fff' : '#333';
				var escapedMsg = jQuery('<div>').text(msg.message).html().replace(/\n/g, '<br>');
				html += '<div class="msg" id="msg-' + msg.id + '" style="margin-bottom: 15px; text-align: ' + align + ';">';
				html += '<div style="font-size: 10px; color: #999; margin-bottom: 2px;">' + (msg.sender_name || '<?php _e("Participant", "agency-nexus"); ?>') + '</div>';
				html += '<div style="background: ' + bg + '; color: ' + color + '; padding: 10px; border-radius: 10px; display: inline-block; max-width: 80%; position:relative; text-align: left;">' + escapedMsg;
				html += '<span class="delete-msg" onclick="deleteMessage(' + msg.id + ')" style="cursor:pointer; font-size:10px; opacity:0.5; margin-left:10px;">×</span>';
				html += '</div></div>';
			});
			if (html === '') {
				html = '<div class="system-msg" style="text-align: center; color: #999; margin: 20px 0;">No messages yet.</div>';
			}
			jQuery('#chat-messages').html(html);
			// Scroll to bottom
			var chatMessages = document.getElementById('chat-messages');
			chatMessages.scrollTop = chatMessages.scrollHeight;
		}
	});
}

function deleteMessage(msgId) {
	if (!confirm('Delete message?')) return;
	var jQuery = window.jQuery;
	jQuery.post(ajaxurl, { action: 'an_delete_message', message_id: msgId, security: jQuery('#security').val() }, function() {
		jQuery('#msg-' + msgId).fadeOut();
	});
}

function deleteFile(fileId) {
	if (!confirm('Delete file?')) return;
	var jQuery = window.jQuery;
	jQuery.post(ajaxurl, { action: 'an_delete_file', file_id: fileId, security: jQuery('#security').val() }, function() {
		jQuery('#file-' + fileId).fadeOut();
	});
}

function loadFiles(id) {
	var jQuery = window.jQuery;
	var data = {
		action: 'an_get_files',
		client_id: id,
		security: jQuery('#security').val()
	};

	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			var html = '';
			response.data.forEach(function(file) {
				var escapedFileName = jQuery('<div>').text(file.file_name).html();
				html += '<li id="file-' + file.id + '" style="padding: 10px; background: #fff; border: 1px solid #eee; margin-bottom: 5px; display: flex; justify-content: space-between;">';
				html += '<span>' + escapedFileName + '</span>';
				html += '<div><a href="' + file.file_url + '" class="button button-small" target="_blank">Download</a> ';
				html += '<button onclick="deleteFile(' + file.id + ')" class="button button-small" style="color:red;">×</button></div>';
				html += '</li>';
			});
			if (html === '') {
				html = '<p style="color: #999; text-align: center;">No files shared yet.</p>';
			}
			jQuery('#shared-files-list').html(html);
		}
	});
}

jQuery(document).ready(function($) {
	// Auto-select client if there is only one
	if ($('.client-item').length === 1) {
		$('.client-item').first().click();
	}

	$('#canned-response-select').on('change', function() {
		var content = $(this).val();
		if (content) {
			$('#chat-message-text').val($('#chat-message-text').val() + content);
			$(this).val('');
		}
	});

	$('#an_ai_suggest_msg').on('click', function(e) {
		e.preventDefault();
		var clientId = $('#chat-client-id').val();
		if (!clientId) {
			alert('Please select a client first.');
			return;
		}
		$('#an_ai_suggest_msg').prop('disabled', true);
		$('#an_ai_msg_loading').show();
		$.post(ajaxurl, {
			action: 'an_ai_suggest_response',
			client_id: clientId,
			security: $('#security').val()
		}, function(response) {
			$('#an_ai_suggest_msg').prop('disabled', false);
			$('#an_ai_msg_loading').hide();
			if (response.success && response.data.suggestion) {
				$('#chat-message-text').val(response.data.suggestion);
			} else {
				alert('AI Copilot was unable to draft a suggestion at this time.');
			}
		});
	});

	$('.chat-tab-btn').on('click', function() {
		$('.chat-tab-btn').removeClass('active');
		$(this).addClass('active');
		$('.chat-pane').hide();
		$('#chat-' + $(this).data('tab')).show();
		if ($(this).data('tab') === 'messages') {
			$('#chat-input').show();
		} else {
			$('#chat-input').hide();
		}
	});

	$('#share-file-btn').on('click', function(e) {
		e.preventDefault();
		const client_id = $('#chat-client-id').val();
		var file_frame = wp.media({
			title: 'Select File to Share',
			multiple: false
		}).open().on('select', function() {
			var attachment = file_frame.state().get('selection').first().toJSON();
			var data = {
				action: 'an_share_file',
				client_id: client_id,
				file_url: attachment.url,
				file_name: attachment.filename,
				security: $('#security').val()
			};
			$.post(ajaxurl, data, function() {
				loadFiles(client_id);
			});
		});
	});

	$('#an-message-form').on('submit', function(e) {
		e.preventDefault();
		const $msgInput = $('#chat-message-text');
		const msgText = $msgInput.val();
		const clientId = $('#chat-client-id').val();
		const $btn = $('#send-msg-btn');

		if (!msgText.trim()) return;

		const data = $(this).serialize() + '&action=an_send_message';
		$msgInput.prop('disabled', true);
		$btn.prop('disabled', true);
		$.post(ajaxurl, data, function(response) {
			if (response.success) {
				$msgInput.val('');
				loadMessages(clientId);
			} else {
				alert('Error: ' + (response.data || 'Unknown error occurred while sending message.'));
			}
		}).fail(function() {
			alert('Failed to send message. Please check your connection and try again.');
		}).always(function() {
			$msgInput.prop('disabled', false).focus();
			$btn.prop('disabled', false);
		});
	});
});
</script>
