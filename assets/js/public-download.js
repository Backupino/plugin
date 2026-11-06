function downloadBoth() {
	var cfg = window.backupinoDownloadConfig || {};
	var f1 = document.createElement('iframe');
	f1.style.display = 'none';
	document.body.appendChild(f1);
	f1.src = cfg.archiveUrl || '';

	setTimeout(function () {
		var f2 = document.createElement('iframe');
		f2.style.display = 'none';
		document.body.appendChild(f2);
		f2.src = cfg.installerUrl || '';
	}, 1200);
}
