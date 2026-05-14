// ===============================================
// BillingReferrals Plugin - Frontend JavaScript
// ===============================================

(function () {
	'use strict';

	const API_VISIT = '/api/billingreferrals/visit';
	const API_CONTEXT = '/api/billingreferrals/register-context';
	const STORAGE_KEY = 'billingreferrals_pending';
	const ROOT_ID = 'billingreferrals-register-root';
	const STYLE_ID = 'billingreferrals-register-styles';

	var registerContext = null;

	const log = function () {
		if (typeof console !== 'undefined' && console.log) {
			console.log.apply(console, ['[BillingReferrals]'].concat(Array.prototype.slice.call(arguments)));
		}
	};

	function isAuthRegisterPath() {
		var p = window.location.pathname || '';
		return p === '/auth/register' || p.indexOf('/auth/register/') === 0;
	}

	function getRefFromUrl() {
		var u = new URL(window.location.href);
		return (
			u.searchParams.get('ref') ||
			u.searchParams.get('referral') ||
			u.searchParams.get('refer') ||
			u.searchParams.get('invite') ||
			u.searchParams.get('code') ||
			''
		).trim();
	}

	function readPending() {
		try {
			var raw = sessionStorage.getItem(STORAGE_KEY);
			return raw ? JSON.parse(raw) : null;
		} catch (e) {
			return null;
		}
	}

	function writePending(obj) {
		try {
			sessionStorage.setItem(STORAGE_KEY, JSON.stringify(obj));
		} catch (e) {}
	}

	function clearPending() {
		try {
			sessionStorage.removeItem(STORAGE_KEY);
		} catch (e) {}
	}

	function findRegisterForm() {
		var forms = document.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) {
			var f = forms[i];
			if (f.classList && f.classList.contains('space-y-5')) {
				return f;
			}
		}
		return null;
	}

	/** Insert above captcha / submit: first direct child of the form that contains captcha or submit. */
	function findReferralInsertAnchor(form) {
		var cap = form.querySelector('.frc-captcha');
		if (cap) {
			var n = cap;
			while (n && n.parentElement !== form) {
				n = n.parentElement;
			}
			if (n && n.parentElement === form) {
				return n;
			}
		}
		var btn = form.querySelector('button[type="submit"]');
		if (btn) {
			var n2 = btn;
			while (n2 && n2.parentElement !== form) {
				n2 = n2.parentElement;
			}
			if (n2 && n2.parentElement === form) {
				return n2;
			}
		}
		return null;
	}

	function ensureStyles() {
		if (document.getElementById(STYLE_ID)) return;
		var s = document.createElement('style');
		s.id = STYLE_ID;
		s.textContent =
			'#' +
			ROOT_ID +
			'{' +
			'font-family:var(--app-font-family,system-ui,-apple-system,sans-serif);' +
			'margin-top:0.25rem;' +
			'margin-bottom:1rem;' +
			'padding:1rem 1rem 1.125rem;' +
			'border-radius:0.75rem;' +
			'border:1px solid hsl(var(--border, 0 0% 18%));' +
			'background:hsl(var(--card, 0 0% 8%) / 0.92);' +
			'color:hsl(var(--foreground, 0 0% 98%));' +
			'box-sizing:border-box;' +
			'}' +
			'#' +
			ROOT_ID +
			' .br-banner{' +
			'margin:0 0 0.9rem 0;' +
			'padding:0.8rem 0.95rem;' +
			'border-radius:0.65rem;' +
			'background:linear-gradient(135deg,hsl(var(--primary,262 83% 58%)/0.2),hsl(var(--muted,0 0% 14%)/0.55));' +
			'border:1px solid hsl(var(--primary,262 83% 58%)/0.35);' +
			'border-left:4px solid hsl(var(--primary,262 83% 58%));' +
			'}' +
			'#' +
			ROOT_ID +
			' .br-banner-kicker{font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;color:hsl(var(--muted-foreground,0 0% 62%));margin:0 0 0.3rem;}' +
			'#' +
			ROOT_ID +
			' .br-banner-code{font-size:1.05rem;font-weight:800;font-family:ui-monospace,monospace;letter-spacing:0.03em;margin:0 0 0.35rem;line-height:1.25;word-break:break-all;}' +
			'#' +
			ROOT_ID +
			' .br-banner-bonus{font-size:0.8125rem;color:hsl(var(--muted-foreground,0 0% 74%));line-height:1.4;margin:0;}' +
			'#' +
			ROOT_ID +
			' .br-title{font-size:0.8125rem;font-weight:600;letter-spacing:0.02em;color:hsl(var(--muted-foreground, 0 0% 65%));margin:0 0 0.35rem;}' +
			'#' +
			ROOT_ID +
			' .br-headline{font-size:0.9375rem;font-weight:600;margin:0 0 0.75rem;line-height:1.35;}' +
			'#' +
			ROOT_ID +
			' .br-label{display:block;font-size:0.8125rem;font-weight:600;margin-bottom:0.35rem;}' +
			'#' +
			ROOT_ID +
			' .br-input{' +
			'width:100%;box-sizing:border-box;height:2.75rem;padding:0 0.75rem;' +
			'border-radius:0.75rem;border:1px solid hsl(var(--border, 0 0% 20%));' +
			'background:hsl(var(--muted, 0 0% 14%) / 0.45);' +
			'color:inherit;font-size:0.875rem;font-weight:600;font-family:ui-monospace,monospace;' +
			'letter-spacing:0.02em;' +
			'}' +
			'#' +
			ROOT_ID +
			' .br-input:focus{outline:none;box-shadow:0 0 0 2px hsl(var(--ring, 262 83% 58%) / 0.35);border-color:hsl(var(--primary, 262 83% 58%));}' +
			'#' +
			ROOT_ID +
			' .br-banner.br-banner-error{' +
			'border-color:hsl(0 72% 45% / 0.45);' +
			'border-left-color:hsl(0 72% 50%);' +
			'background:linear-gradient(135deg,hsl(0 72% 42% / 0.14),hsl(var(--muted,0 0% 14%)/0.55));' +
			'}' +
			'#' +
			ROOT_ID +
			' .br-banner-error .br-banner-bonus,' +
			'#' +
			ROOT_ID +
			' .br-banner-error .br-banner-kicker,' +
			'#' +
			ROOT_ID +
			' .br-banner-error .br-banner-code{color:hsl(0 0% 98%);}' +
			'#' +
			ROOT_ID +
			' .br-sub.br-sub-error{color:hsl(0 86% 82%);}' +
			'#' +
			ROOT_ID +
			' .br-sub{font-size:0.8125rem;color:hsl(var(--muted-foreground, 0 0% 70%));margin:0.65rem 0 0;line-height:1.45;}' +
			'#' +
			ROOT_ID +
			' .br-hint{font-size:0.75rem;color:hsl(var(--muted-foreground, 0 0% 55%));margin:0.45rem 0 0;line-height:1.35;}';
		document.head.appendChild(s);
	}

	function defaultCoinsHint(defaultCoins) {
		if (typeof defaultCoins === 'number' && defaultCoins > 0) {
			return (
				'Optional: enter a friend\'s referral code above. A valid code can give you up to ' +
				defaultCoins +
				' extra coins when you sign up.'
			);
		}
		return 'Optional: enter a referral code above. If it is valid, you may receive an extra coin bonus at signup.';
	}

	function visitErrorMessage(j) {
		if (!j) {
			return 'Could not verify this code. Try again.';
		}
		var errCode = j.error_code || '';
		if (j.errors && j.errors.length && j.errors[0] && j.errors[0].code) {
			errCode = errCode || j.errors[0].code;
		}
		var msg = j.message || j.error_message || '';
		if (j.errors && j.errors.length && j.errors[0] && j.errors[0].detail) {
			msg = msg || j.errors[0].detail;
		}
		if (errCode === 'CODE_INVALID') {
			return msg || 'This referral code is invalid or is no longer active.';
		}
		if (j.success === false && msg) {
			return msg;
		}
		return msg || 'Could not apply this referral code.';
	}

	function buildRoot(data) {
		ensureStyles();
		var root = document.createElement('div');
		root.id = ROOT_ID;
		root.setAttribute('data-billingreferrals', '1');

		var defaultCoins =
			typeof data.defaultRefereeCoins === 'number' ? data.defaultRefereeCoins : null;
		var appliedCoins =
			typeof data.referee_credits === 'number' ? data.referee_credits : null;

		var isFetchingCode = false;
		var codeErrorMessage = null;
		var visitSeq = 0;

		var banner = document.createElement('div');
		banner.className = 'br-banner';
		var bannerKicker = document.createElement('div');
		bannerKicker.className = 'br-banner-kicker';
		var bannerCode = document.createElement('div');
		bannerCode.className = 'br-banner-code';
		var bannerBonus = document.createElement('p');
		bannerBonus.className = 'br-banner-bonus';
		banner.appendChild(bannerKicker);
		banner.appendChild(bannerCode);
		banner.appendChild(bannerBonus);

		var headline = document.createElement('p');
		headline.className = 'br-headline';
		headline.textContent = 'Add or change your code (optional)';

		var label = document.createElement('label');
		label.className = 'br-label';
		label.setAttribute('for', 'billingreferrals-code-input');
		label.textContent = 'Code';

		var input = document.createElement('input');
		input.type = 'text';
		input.id = 'billingreferrals-code-input';
		input.className = 'br-input';
		input.autocomplete = 'off';
		input.spellcheck = false;
		input.placeholder = 'Enter a code';
		input.value = data.code || '';

		var sub = document.createElement('p');
		sub.className = 'br-sub';

		function refreshAll() {
			var v = String(input.value || '').trim();
			sub.classList.remove('br-sub-error');
			banner.classList.remove('br-banner-error');
			if (!v) {
				appliedCoins = null;
				codeErrorMessage = null;
				isFetchingCode = false;
				bannerKicker.textContent = 'Referral bonus';
				bannerCode.textContent = '—';
				if (typeof defaultCoins === 'number' && defaultCoins > 0) {
					bannerBonus.textContent =
						'Valid codes can unlock up to ' + defaultCoins + ' extra coins at signup.';
				} else {
					bannerBonus.textContent = 'Enter a valid code below for a signup coin bonus.';
				}
				sub.textContent = defaultCoinsHint(defaultCoins);
				return;
			}
			if (typeof appliedCoins === 'number' && appliedCoins > 0) {
				bannerKicker.textContent = 'Your referral code';
				bannerCode.textContent = v;
				bannerBonus.textContent =
					'You will get ' + appliedCoins + ' extra coins when your account is created.';
				sub.textContent = bannerBonus.textContent;
				return;
			}
			if (typeof appliedCoins === 'number' && appliedCoins === 0) {
				bannerKicker.textContent = 'Your referral code';
				bannerCode.textContent = v;
				bannerBonus.textContent = 'This referral will be linked to your signup.';
				sub.textContent = bannerBonus.textContent;
				return;
			}
			if (codeErrorMessage) {
				banner.classList.add('br-banner-error');
				sub.classList.add('br-sub-error');
				bannerKicker.textContent = 'Invalid referral code';
				bannerCode.textContent = v;
				bannerBonus.textContent = codeErrorMessage;
				sub.textContent = codeErrorMessage;
				return;
			}
			if (isFetchingCode) {
				bannerKicker.textContent = 'Your referral code';
				bannerCode.textContent = v;
				bannerBonus.textContent = 'Checking this code…';
				sub.textContent = 'Hang on while we verify this referral code.';
				return;
			}
			bannerKicker.textContent = 'Your referral code';
			bannerCode.textContent = v;
			bannerBonus.textContent = 'Enter a valid code or change the one above.';
			sub.textContent = 'If this code is valid, your extra coin bonus will show here.';
		}

		refreshAll();

		var hint = document.createElement('p');
		hint.className = 'br-hint';
		hint.textContent =
			'We save your choice in a cookie. Change the code only if you want a different referral.';

		var debounceTimer = null;
		function applyCode(newCode) {
			var c = String(newCode || '').trim();
			if (!c) {
				visitSeq += 1;
				isFetchingCode = false;
				codeErrorMessage = null;
				refreshAll();
				return;
			}
			visitSeq += 1;
			var mySeq = visitSeq;
			isFetchingCode = true;
			codeErrorMessage = null;
			appliedCoins = null;
			refreshAll();
			fetch(API_VISIT, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ code: c }),
				credentials: 'include',
			})
				.then(function (r) {
					return r.json().then(function (j) {
						return { ok: r.ok, j: j };
					});
				})
				.then(function (res) {
					if (mySeq !== visitSeq) {
						return;
					}
					isFetchingCode = false;
					if (res.ok && res.j && res.j.success && res.j.data) {
						var rc =
							typeof res.j.data.referee_credits === 'number'
								? res.j.data.referee_credits
								: null;
						codeErrorMessage = null;
						appliedCoins = rc;
						writePending({ code: c, referee_credits: rc });
						refreshAll();
					} else {
						codeErrorMessage = visitErrorMessage(res.j);
						appliedCoins = null;
						refreshAll();
					}
				})
				.catch(function () {
					if (mySeq !== visitSeq) {
						return;
					}
					isFetchingCode = false;
					codeErrorMessage = 'Network error. Check your connection and try again.';
					appliedCoins = null;
					refreshAll();
				});
		}

		input.addEventListener('input', function () {
			clearTimeout(debounceTimer);
			if (codeErrorMessage) {
				codeErrorMessage = null;
				refreshAll();
			}
			debounceTimer = setTimeout(function () {
				applyCode(input.value);
			}, 500);
		});
		input.addEventListener('blur', function () {
			clearTimeout(debounceTimer);
			applyCode(input.value);
		});

		if (String(input.value || '').trim() && typeof data.referee_credits !== 'number') {
			setTimeout(function () {
				applyCode(input.value);
			}, 0);
		}

		root.appendChild(banner);
		root.appendChild(headline);
		root.appendChild(label);
		root.appendChild(input);
		root.appendChild(sub);
		root.appendChild(hint);

		return root;
	}

	function shouldOfferReferralUI() {
		if (!registerContext) return false;
		if (!registerContext.enabled) return false;
		var refUrl = getRefFromUrl();
		var pending = readPending();
		var hasPendingCode = !!(pending && String(pending.code || '').trim());
		// After trackVisit we strip ?ref= and set hasCookie — still show UI while session has pending.
		if (registerContext.hasCookie && !refUrl && !hasPendingCode) {
			return false;
		}
		return true;
	}

	function injectRegisterChrome() {
		if (!isAuthRegisterPath()) return false;
		if (document.getElementById(ROOT_ID)) return true;
		if (!shouldOfferReferralUI()) return false;

		var pending = readPending();
		var fromUrl = getRefFromUrl();
		var code = (pending && pending.code) || fromUrl || '';

		var form = findRegisterForm();
		if (!form) {
			log('Register form not ready yet (waiting for layout)');
			return false;
		}

		var defaultCoins =
			registerContext && typeof registerContext.refereeCredits === 'number'
				? registerContext.refereeCredits
				: null;
		var pendingRc =
			pending && typeof pending.referee_credits === 'number' ? pending.referee_credits : null;

		var root = buildRoot({
			code: code,
			referee_credits: pendingRc,
			defaultRefereeCoins: defaultCoins,
		});

		var anchor = findReferralInsertAnchor(form);
		if (anchor) {
			form.insertBefore(root, anchor);
		} else {
			form.appendChild(root);
		}
		log('Injected referral UI into register form (below main fields)');
		return true;
	}

	function trackReferralVisit(code) {
		return fetch(API_VISIT, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ code: code }),
			credentials: 'include',
		})
			.then(function (r) {
				return r.json().then(function (j) {
					return { ok: r.ok, j: j };
				});
			})
			.then(function (res) {
				if (res.ok && res.j && res.j.success) {
					var d = res.j.data || {};
					var rc = typeof d.referee_credits === 'number' ? d.referee_credits : null;
					writePending({
						code: d.code || code,
						referee_credits: rc,
					});
					log('Referral visit tracked');
					if (window.history && window.history.replaceState) {
						var url = new URL(window.location.href);
						['ref', 'referral', 'refer', 'invite', 'code'].forEach(function (k) {
							url.searchParams.delete(k);
						});
						window.history.replaceState({}, document.title, url.toString());
					}
					if (registerContext) {
						registerContext.hasCookie = true;
					}
					return true;
				}
				log('Track visit failed', res.j);
				return false;
			})
			.catch(function (e) {
				console.error('[BillingReferrals]', e);
				return false;
			});
	}

	function fetchRegisterContext() {
		return fetch(API_CONTEXT, { credentials: 'include' })
			.then(function (r) {
				return r.json().then(function (j) {
					return { ok: r.ok, j: j };
				});
			})
			.then(function (res) {
				if (res.ok && res.j && res.j.success && res.j.data) {
					var d = res.j.data;
					return {
						enabled: d.referrals_enabled === true,
						hasCookie: d.has_referral_cookie === true,
						refereeCredits:
							typeof d.referee_credits === 'number' ? d.referee_credits : null,
					};
				}
				return { enabled: true, hasCookie: false, refereeCredits: null };
			})
			.catch(function () {
				return { enabled: true, hasCookie: false, refereeCredits: null };
			});
	}

	function checkReferralCodeFromUrl() {
		var ref = getRefFromUrl();
		if (!ref) {
			log('No referral code in URL');
			return Promise.resolve(false);
		}
		log('Referral code in URL:', ref);
		writePending({ code: ref, referee_credits: null });
		return trackReferralVisit(ref);
	}

	function tryInjectLoop() {
		if (!isAuthRegisterPath()) return;
		if (injectRegisterChrome()) return;
		var tries = 0;
		var id = setInterval(function () {
			tries += 1;
			if (injectRegisterChrome() || tries > 120) {
				clearInterval(id);
			}
		}, 250);
	}

	function init() {
		log('Init', window.location.pathname);
		fetchRegisterContext().then(function (ctx) {
			registerContext = ctx;
			if (ctx.hasCookie && !getRefFromUrl()) {
				clearPending();
				log('Referral cookie already set; skipping register UI');
			}
			return checkReferralCodeFromUrl();
		}).finally(function () {
			tryInjectLoop();
		});
	}

	var observer = new MutationObserver(function () {
		if (!isAuthRegisterPath()) return;
		if (document.getElementById(ROOT_ID)) return;
		if (!registerContext) return;
		if (!shouldOfferReferralUI()) return;
		injectRegisterChrome();
	});

	if (document.body) {
		observer.observe(document.body, { childList: true, subtree: true });
	} else {
		document.addEventListener('DOMContentLoaded', function () {
			if (document.body) observer.observe(document.body, { childList: true, subtree: true });
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.BillingReferralsPlugin = {
		trackReferralVisit: trackReferralVisit,
		injectRegisterChrome: injectRegisterChrome,
	};
})();
