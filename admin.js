/*function revealSenderDisplay() {
	document.getElementById('j1sa-sender-display').style.display = 'initial';
}

if (document.getElementById('j1sa-sender-display').value) {
	// revealSenderDisplay();
}*/

document.addEventListener("DOMContentLoaded", function() { 
	document.getElementById('pick-mobile-number').onclick = function(ev){
		var popup = window.open('about:blank', 'j1saPopup', 'resizable=yes, scrollbars=yes, titlebar=yes, width=1000, height=600, top=10, left=10');
		var form = jQuery(ev.currentTarget).parents('form')[0];
		
		var backup = {
			target: form.target,
			action: jQuery(form).attr('action'),
		};
			
		form.target = 'j1saPopup';
		jQuery(form).attr('action', 'https://www.just1stepahead.com/sms/numbers.php'); // clash with an input named action
		
		document.getElementById('j1sa-email2').value = document.getElementById('j1sa-username').value;
		document.getElementById('j1sa-pwd2').value = document.getElementById('j1sa-password').value;
	
		window.addEventListener('message', function(ev) {
			var sender = ev.data.split('//'); // shape: sender ID, sender display name, sender phone number
			if (sender.length !== 3) {
				alert('Something went wrong while selecting sender phone number');
				throw new Error('unknown sender ID return value: ' + sender);
			}
			console.log('cb sender ID', sender);
			document.getElementById('j1sa-sender-id').value = sender[0];
			document.getElementById('j1sa-sender-display').value = sender[1];
			document.getElementById('j1sa-recipient').value = sender[2];
			// revealSenderDisplay();
			popup.close();
		}, {
			passive: true,
			once: true,
		});
		
		setTimeout(function() {
			form.target = backup.target;
			jQuery(form).attr('action', backup.action);
		}, 1500); // restore original target & action after 1.5 secs
	}
});
