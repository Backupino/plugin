(function () {
	document.addEventListener('DOMContentLoaded', function () {
		var btn = document.getElementById('backupino-download-both');
		if (!btn) {
			return;
		}

		btn.addEventListener('click', function (e) {
			e.preventDefault();
			var archiveUrl = btn.getAttribute('data-archive-url');
			var installerUrl = btn.getAttribute('data-installer-url');

			if (archiveUrl) {
				var f1 = document.createElement('iframe');
				f1.style.display = 'none';
				document.body.appendChild(f1);
				f1.src = archiveUrl;
			}

			if (installerUrl) {
				setTimeout(function () {
					var f2 = document.createElement('iframe');
					f2.style.display = 'none';
					document.body.appendChild(f2);
					f2.src = installerUrl;
				}, 1200);
			}
		});
	});
})();
