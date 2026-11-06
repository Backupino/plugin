(function () {
	var rootEl = document.getElementById("backupino-root");
	var config = {};
	try {
		config = JSON.parse((rootEl && rootEl.getAttribute("data-config")) || "{}");
	} catch (e) {
		config = (window.BACKUPINO_INSTALLER && typeof window.BACKUPINO_INSTALLER === "object") ? window.BACKUPINO_INSTALLER : {};
	}
	window.BACKUPINO_INSTALLER = config;

	var currentLang = (config.locale && config.locale.indexOf("fa") === 0) ? "fa" : "en";
	var currentStep = 1;
	var envPasses = !!config.envPasses;
	var I18N = {
		en: {
			lead: "Restoring your site — step by step.",
			env_check: "Environment check",
			continue: "Continue",
			back_btn: "← Back",
			package_lbl: "Package",
			built_lbl: "built",
			installer_password: "Installer Password",
			database: "Database",
			db_host: "Host",
			db_port: "Port",
			db_name: "Database name",
			db_user: "Username",
			db_pass: "Password",
			prefix: "Table prefix",
			target_path: "Target Path",
			new_url: "New site URL",
			new_url_hint: "Auto-detected from current domain and path.",
			connect_btn: "Connect & continue",
			working: "Working…",
			ready_title: "✓ Your site is ready",
			cleanup_btn: "Delete installer files & Login",
			open_admin: "Open wp-login.php →",
			empty_db_lbl: "Empty existing database tables before import (Recommended)",
			ext_files: "Extracting files",
			imp_db: "Importing database",
			rew_urls: "Rewriting URLs",
			cfg_wp: "Configuring WordPress",
			do_not_close_hint: "Please keep this browser window open and do not refresh or navigate away until the restoration is completely finished.",
			cleaned: "Installer files deleted. Redirecting to login…",
			s1: "1. Environment",
			s2: "2. Database",
			s3: "3. Files",
			s4: "4. Database import",
			s5: "5. URLs",
			s6: "6. Done"
		},
		fa: {
			lead: "بازیابی وب‌سایت شما — گام‌به‌گام و آسان.",
			env_check: "بررسی نیازمندی‌های محیط سرور",
			continue: "ادامه و مرحله بعد",
			back_btn: "← مرحله قبل",
			package_lbl: "بسته پشتیبان",
			built_lbl: "ساخته شده در",
			installer_password: "رمز عبور نصاب",
			database: "پایگاه داده (دیتابیس)",
			db_host: "میزبان دیتابیس (Host)",
			db_port: "پورت (اختیاری)",
			db_name: "نام دیتابیس",
			db_user: "نام کاربری دیتابیس",
			db_pass: "رمز عبور دیتابیس",
			prefix: "پیشوند جداول",
			target_path: "مسیر استخراج در سرور",
			new_url: "آدرس جدید سایت (URL)",
			new_url_hint: "به صورت خودکار بر اساس دامنه و پوشه فعلی شناسایی شد.",
			connect_btn: "اتصال به دیتابیس و شروع بازیابی",
			working: "در حال پردازش…",
			ready_title: "✓ وبسایت شما با موفقیت بازیابی شد",
			cleanup_btn: "حذف فایل‌های نصبی و ورود به مدیریت",
			open_admin: "ورود مستقیم به wp-login.php ←",
			empty_db_lbl: "پاک‌سازی و حذف جداول موجود در دیتابیس قبل از درون‌ریزی (پیشنهادی)",
			ext_files: "در حال استخراج فایل‌ها",
			imp_db: "در حال درون‌ریزی دیتابیس",
			rew_urls: "در حال به‌روزرسانی آدرس‌ها و جداول",
			cfg_wp: "پیکربندی وردپرس",
			do_not_close_hint: "لطفاً این پنجره مرورگر را تا تکمیل کامل بازگردانی نبندید و از بازآوری (رفرش) صفحه خودداری فرمایید.",
			cleaned: "فایل‌های نصبی با موفقیت حذف شدند. در حال هدایت به صفحه ورود…",
			s1: "۱. نیازمندی‌ها",
			s2: "۲. دیتابیس",
			s3: "۳. استخراج فایل‌ها",
			s4: "۴. درون‌ریزی داده",
			s5: "۵. بازنویسی لینک‌ها",
			s6: "۶. پایان"
		}
	};

	function setLanguage(lang) {
		currentLang = lang;
		document.documentElement.setAttribute("lang", lang);
		document.documentElement.setAttribute("dir", lang === "fa" ? "rtl" : "ltr");

		var btns = document.querySelectorAll(".lang-btn");
		for (var i = 0; i < btns.length; i++) {
			btns[i].classList.toggle("active", btns[i].getAttribute("data-lang") === lang);
		}

		var elements = document.querySelectorAll("[data-i18n]");
		for (var j = 0; j < elements.length; j++) {
			var key = elements[j].getAttribute("data-i18n");
			if (I18N[lang] && I18N[lang][key]) {
				elements[j].textContent = I18N[lang][key];
			}
		}

		var lead = document.getElementById("txt-lead");
		if (lead && I18N[lang].lead) {
			lead.textContent = I18N[lang].lead;
		}

		var stepKeys = ["s1", "s2", "s3", "s4", "s5", "s6"];
		for (var k = 0; k < stepKeys.length; k++) {
			var stepEl = document.getElementById("step-nav-" + (k + 1));
			if (stepEl && I18N[lang][stepKeys[k]]) {
				stepEl.textContent = I18N[lang][stepKeys[k]];
			}
		}
	}

	var langSwitcher = document.getElementById("lang-switch");
	if (langSwitcher) {
		langSwitcher.addEventListener("click", function (e) {
			var target = e.target;
			if (target && target.hasAttribute("data-lang")) {
				setLanguage(target.getAttribute("data-lang"));
			}
		});
	}

	if (currentLang === "fa") {
		setLanguage("fa");
	}

	function updateStepNav(activeStepIndex) {
		currentStep = activeStepIndex;
		for (var i = 1; i <= 6; i++) {
			var sEl = document.getElementById("step-nav-" + i);
			if (sEl) {
				sEl.classList.toggle("active", i === activeStepIndex);
			}
		}
	}

	function getToken() {
		return (window.BACKUPINO_INSTALLER && window.BACKUPINO_INSTALLER.token) ? window.BACKUPINO_INSTALLER.token : "";
	}

	var log = document.getElementById("log");
	var bar = document.getElementById("bar");
	var title = document.getElementById("progress-title");
	var busy = false;

	function api(action, data) {
		var params = Object.assign({ action: action, token: getToken() }, data || {});
		var body = new URLSearchParams(params);
		return fetch(window.location.pathname, {
			method: "POST",
			body: body,
			credentials: "same-origin",
			headers: { "Content-Type": "application/x-www-form-urlencoded" }
		}).then(function (r) {
			return r.text().then(function (raw) {
				try {
					return JSON.parse(raw);
				} catch (e) {
					return { ok: false, error: raw.trim() ? raw.substring(0, 300) : ("Empty response from server (HTTP " + r.status + ")") };
				}
			});
		});
	}

	function note(text) {
		if (!log) { return; }
		log.textContent += text + "\n";
		log.scrollTop = log.scrollHeight;
	}

	function show(id) {
		["step1", "step2", "step3", "step4"].forEach(function (step) {
			var el = document.getElementById(step);
			if (el) { el.className = step === id ? "" : "hidden"; }
		});
	}

	function fail(message, field) {
		var el = document.getElementById(field || "error3");
		if (el) {
			el.textContent = message;
			el.style.display = "block";
		}
		note("\n❌ [ERROR]: " + message);
		if (title) {
			title.textContent = "❌ " + ((currentLang === "fa") ? "خطا در عملیات" : "Operation failed");
			title.style.color = "#dc2626";
		}
		if (bar) {
			bar.style.background = "#dc2626";
		}
		busy = false;
	}

	function loop(action, label, onDone) {
		if (title) {
			title.textContent = label + "…";
			title.style.color = "var(--ink)";
		}
		function tick() {
			api(action).then(function (res) {
				if (!res.ok) { fail(res.error || "Action failed: " + action); return; }
				if (res.log) { note(res.log); }
				if (typeof res.percent === "number") {
					var pct = Math.max(0, Math.min(100, Math.round(res.percent)));
					if (bar) { bar.style.width = pct + "%"; }
				}
				if (res.done) { onDone(); } else { tick(); }
			}).catch(function (err) { fail(String(err)); });
		}
		tick();
	}

	var startBtn = document.getElementById("start");
	if (startBtn) {
		startBtn.addEventListener("click", function () {
			if (!envPasses) { return; }
			show("step2");
			updateStepNav(2);
		});
	}

	var backBtn = document.getElementById("back-to-step1") || document.getElementById("back-step1");
	if (backBtn) {
		backBtn.addEventListener("click", function (e) {
			if (e && e.preventDefault) { e.preventDefault(); }
			show("step1");
			updateStepNav(1);
		});
	}

	var dbform = document.getElementById("dbform");
	if (dbform) {
		dbform.addEventListener("submit", function (e) {
			e.preventDefault();
		});
	}

	var connectBtn = document.getElementById("connect");
	if (connectBtn) {
		connectBtn.addEventListener("click", function (e) {
			if (e && e.preventDefault) { e.preventDefault(); }
			if (busy) { return; }
			busy = true;
			var err2 = document.getElementById("error2");
			if (err2) { err2.textContent = ""; }
			var btn = connectBtn;
			var oldText = btn.textContent;
			btn.textContent = (I18N[currentLang] && I18N[currentLang].working) ? I18N[currentLang].working : "Connecting…";
			btn.disabled = true;

			var getVal = function (id) {
				var el = document.getElementById(id);
				return el ? el.value : "";
			};
			var getChecked = function (id) {
				var el = document.getElementById(id);
				return (el && el.checked) ? "1" : "0";
			};

			var payload = {
				installer_password: getVal("installer_password"),
				db_host: getVal("db_host"),
				db_port: getVal("db_port"),
				db_name: getVal("db_name"),
				db_user: getVal("db_user"),
				db_pass: getVal("db_pass"),
				prefix: getVal("prefix"),
				new_url: getVal("new_url"),
				empty_db: getChecked("empty_db")
			};

			api("connect", payload).then(function (res) {
				btn.disabled = false;
				btn.textContent = oldText;
				if (!res.ok) {
					fail(res.error || "Connection failed.", "error2");
					return;
				}
				show("step3");
				updateStepNav(3);
				note("Connected to database `" + res.db + "` (found " + res.tables_found + " existing tables).");
				var extLabel = (I18N[currentLang] && I18N[currentLang].ext_files) ? I18N[currentLang].ext_files : "Extracting files";
				loop("extract", extLabel, function () {
					note("Files extracted.");
					updateStepNav(4);
					var impLabel = (I18N[currentLang] && I18N[currentLang].imp_db) ? I18N[currentLang].imp_db : "Importing database";
					loop("import", impLabel, function () {
						note("Database imported.");
						updateStepNav(5);
						var rewLabel = (I18N[currentLang] && I18N[currentLang].rew_urls) ? I18N[currentLang].rew_urls : "Rewriting URLs";
						loop("rewrite", rewLabel, function () {
							note("URLs rewritten.");
							updateStepNav(6);
							api("config").then(function (final) {
								if (!final.ok) { fail(final.error || "Configuration failed."); return; }
								show("step4");
								var siteUrl = final.url || "";
								var adminUrl = final.admin || (siteUrl ? siteUrl.replace(/\/+$/, "") + "/wp-login.php" : "/wp-login.php");
								window.__adminUrl = adminUrl;
								window.__siteUrl = siteUrl;
								var doneUrlEl = document.getElementById("done-url");
								if (doneUrlEl) { doneUrlEl.textContent = siteUrl; }
							});
						});
					});
				});
			}).catch(function (err) { fail(String(err), "error2"); });
		});
	}

	var cleanupBtn = document.getElementById("cleanup");
	if (cleanupBtn) {
		cleanupBtn.addEventListener("click", function (e) {
			if (e && e.preventDefault) { e.preventDefault(); }
			var btn = cleanupBtn;
			btn.disabled = true;
			btn.textContent = (currentLang === "fa") ? "در حال حذف فایل‌ها…" : "Deleting files…";
			api("cleanup").then(function (res) {
				if (!res.ok) { fail(res.error || "Cleanup failed.", "error4"); btn.disabled = false; return; }
				btn.textContent = (I18N[currentLang] && I18N[currentLang].cleaned) ? I18N[currentLang].cleaned : "Installer files deleted.";
				window.setTimeout(function () {
					window.location.href = window.__adminUrl || "/wp-login.php";
				}, 1000);
			}).catch(function (err) {
				fail(String(err), "error4");
				btn.disabled = false;
			});
		});
	}
})();
