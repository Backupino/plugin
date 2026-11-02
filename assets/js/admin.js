/* global BackupinoData, fetch, FormData */
( function () {
	'use strict';

	var data = window.BackupinoData || { restUrl: '', nonce: '', strings: {} };

	function request( path, options ) {
		var opts = options || {};
		opts.headers = Object.assign(
			{
				'Content-Type': 'application/json',
				'X-WP-Nonce': data.nonce
			},
			opts.headers || {}
		);
		opts.credentials = 'same-origin';

		return fetch( data.restUrl + path, opts ).then( function ( response ) {
			return response.text().then( function ( text ) {
				var body;
				try {
					body = JSON.parse( text );
				} catch ( e ) {
					var preview = text.replace( /<[^>]*>/g, ' ' ).trim().substring( 0, 150 );
					throw new Error( ( response.status ? 'HTTP ' + response.status + ': ' : '' ) + ( preview || 'Invalid server response.' ) );
				}
				if ( ! response.ok ) {
					throw new Error( body && body.message ? body.message : 'HTTP ' + response.status );
				}
				return body;
			} );
		} );
	}

	function setResult( element, message, isError ) {
		if ( ! element ) {
			return;
		}
		element.textContent = message || '';
		element.className = 'backupino-result ' + ( message ? ( isError ? 'is-error' : 'is-ok' ) : '' );
	}

	function getTimeString() {
		var now = new Date();
		var pad = function ( num ) {
			return num < 10 ? '0' + num : num;
		};
		return pad( now.getHours() ) + ':' + pad( now.getMinutes() ) + ':' + pad( now.getSeconds() );
	}

	function addTerminalLine( text, type ) {
		var terminal = document.getElementById( 'backupino-activity-log' );
		if ( ! terminal ) {
			return;
		}

		var line = document.createElement( 'div' );
		line.className = 'backupino-log-line';

		var timeSpan = document.createElement( 'span' );
		timeSpan.className = 'timestamp';
		timeSpan.textContent = '[' + getTimeString() + '] ';

		var msgSpan = document.createElement( 'span' );
		if ( type === 'error' ) {
			msgSpan.style.color = '#ef4444';
		} else if ( type === 'success' ) {
			msgSpan.style.color = '#10b981';
		} else if ( type === 'stage' ) {
			msgSpan.style.color = '#38bdf8';
			msgSpan.style.fontWeight = 'bold';
		}
		msgSpan.textContent = text;

		line.appendChild( timeSpan );
		line.appendChild( msgSpan );
		terminal.appendChild( line );

		terminal.scrollTop = terminal.scrollHeight;
	}

	function updateStagePills( stageId ) {
		var flow = document.getElementById( 'backupino-stages-flow' );
		if ( ! flow ) {
			return;
		}

		var stagesOrder = [ 'manifest', 'database', 'files', 'installer', 'package' ];
		var currentIndex = stagesOrder.indexOf( stageId );
		if ( currentIndex === -1 && stageId === 'finalize' ) {
			currentIndex = stagesOrder.length - 1;
		}

		stagesOrder.forEach( function ( stage, idx ) {
			var pill = flow.querySelector( '[data-stage="' + stage + '"]' );
			if ( ! pill ) {
				return;
			}
			pill.classList.remove( 'is-active', 'is-done' );
			if ( currentIndex >= 0 ) {
				if ( idx < currentIndex ) {
					pill.classList.add( 'is-done' );
				} else if ( idx === currentIndex ) {
					pill.classList.add( 'is-active' );
				}
			}
		} );
	}

	function progressBar( container, percent, text, stageId, isFinished, isError ) {
		if ( ! container ) {
			return;
		}
		container.classList.remove( 'hidden' );

		var badge = document.getElementById( 'backupino-progress-badge' );
		var barFill = container.querySelector( '.backupino-bar-fill' );
		var label = document.getElementById( 'backupino-progress-text' );

		var clamped = Math.max( 0, Math.min( 100, Math.round( percent ) ) );

		if ( badge ) {
			badge.textContent = clamped + '%';
		}
		if ( barFill ) {
			barFill.style.width = clamped + '%';
		}
		if ( label ) {
			label.textContent = text || '';
		}

		if ( stageId ) {
			updateStagePills( stageId );
		}

		if ( isFinished ) {
			var flow = document.getElementById( 'backupino-stages-flow' );
			if ( flow ) {
				Array.prototype.forEach.call( flow.querySelectorAll( '.backupino-stage-pill' ), function ( p ) {
					p.classList.remove( 'is-active' );
					p.classList.add( 'is-done' );
				} );
			}
		}
	}

	function createBackup( form, progressContainer ) {
		isJobCancelled = false;
		var files = form.querySelector( 'input[name="files"]' );
		var database = form.querySelector( 'input[name="database"]' );
		var note = form.querySelector( 'input[name="note"]' );
		var button = form.querySelector( 'button[type="submit"]' );

		if ( button ) {
			button.disabled = true;
		}

		progressContainer.classList.remove( 'hidden' );
		progressBar( progressContainer, 2, data.strings.running || 'Starting backup job…', 'manifest' );
		addTerminalLine( 'Initializing backup job…', 'stage' );

		return request( 'jobs', {
			method: 'POST',
			body: JSON.stringify( {
				files: files ? files.checked : true,
				database: database ? database.checked : true,
				note: note ? note.value : ''
			} )
		} )
			.then( function ( job ) {
				currentActiveJobId = job.id;
				progressContainer.setAttribute( 'data-active-job', job.id );
				progressContainer.setAttribute( 'data-stage', 'manifest' );
				progressContainer.setAttribute( 'data-progress', '2' );
				addTerminalLine( 'Backup job #' + job.id + ' registered. Beginning execution pipeline…' );
				return step( job.id, progressContainer );
			} )
			.catch( function ( error ) {
				progressBar( progressContainer, 0, ( data.strings.failed || 'Failed' ) + ': ' + error.message, '', false, true );
				addTerminalLine( 'Error: ' + error.message, 'error' );
			} )
			.then( function () {
				if ( button ) {
					button.disabled = false;
				}
			} );
	}

	var lastLoggedMessage = '';
	var retryCount = 0;
	var MAX_RETRIES = 4;
	var isJobCancelled = false;
	var currentActiveJobId = null;
	var currentProgressContainer = null;
	var isStepping = false;
	var stepTimer = null;

	function step( id, progressContainer ) {
		if ( isJobCancelled ) {
			return Promise.resolve();
		}
		currentActiveJobId = id;
		currentProgressContainer = progressContainer;
		isStepping = true;
		if ( stepTimer ) {
			window.clearTimeout( stepTimer );
			stepTimer = null;
		}

		return request( 'jobs/' + id + '/step', { method: 'POST' } )
			.then( function ( job ) {
				isStepping = false;
				retryCount = 0;
				var currentStage = job.stage || '';
				var displayMsg = ( job.stageLabel || data.strings.running ) + ( job.message ? ' — ' + job.message : '' );

				progressBar(
					progressContainer,
					job.progress,
					displayMsg,
					currentStage
				);

				if ( job.message && job.message !== lastLoggedMessage ) {
					lastLoggedMessage = job.message;
					addTerminalLine( ( job.stageLabel ? '[' + job.stageLabel + '] ' : '' ) + job.message );
				}

				if ( job.status === 'completed' ) {
					currentActiveJobId = null;
					progressBar( progressContainer, 100, data.strings.completed || 'Backup completed successfully!', 'package', true );
					addTerminalLine( 'SUCCESS: Package generated and stored successfully!', 'success' );
					window.setTimeout( function () {
						window.location.reload();
					}, 2000 );
					return job;
				}

				if ( job.status === 'failed' || job.status === 'cancelled' ) {
					currentActiveJobId = null;
					progressBar( progressContainer, job.progress, ( data.strings.failed || 'Job failed' ) + ': ' + ( job.message || '' ), currentStage, false, true );
					addTerminalLine( 'FAILED: ' + ( job.message || 'Job aborted.' ), 'error' );
					return job;
				}

				return new Promise( function ( resolve ) {
					stepTimer = window.setTimeout( function () {
						stepTimer = null;
						resolve( step( id, progressContainer ) );
					}, 800 );
				} );
			} )
			.catch( function ( error ) {
				isStepping = false;
				retryCount++;
				if ( retryCount <= MAX_RETRIES ) {
					addTerminalLine( 'Notice: Step paused temporarily (' + error.message + '). Retrying in 2s (attempt ' + retryCount + '/' + MAX_RETRIES + ')…', 'warning' );
					return new Promise( function ( resolve ) {
						stepTimer = window.setTimeout( function () {
							stepTimer = null;
							resolve( step( id, progressContainer ) );
						}, 2000 );
					} );
				}

				currentActiveJobId = null;
				progressBar( progressContainer, 0, ( data.strings.failed || 'Job failed' ) + ': ' + error.message, '', false, true );
				addTerminalLine( 'CRITICAL: Step execution aborted: ' + error.message, 'error' );
				throw error;
			} );
	}

	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden && currentActiveJobId && ! isJobCancelled && ! isStepping && currentProgressContainer ) {
			if ( stepTimer ) {
				window.clearTimeout( stepTimer );
				stepTimer = null;
			}
			step( currentActiveJobId, currentProgressContainer );
		}
	} );

	function bindDashboard() {
		var form = document.getElementById( 'backupino-create' );
		var progressCard = document.getElementById( 'backupino-progress' );

		if ( form && progressCard ) {
			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				createBackup( form, progressCard );
			} );
		}

		if ( progressCard && progressCard.getAttribute( 'data-active-job' ) ) {
			var activeId = progressCard.getAttribute( 'data-active-job' );
			var activeStage = progressCard.getAttribute( 'data-stage' ) || 'manifest';
			var activeProg = parseFloat( progressCard.getAttribute( 'data-progress' ) ) || 0;
			progressBar( progressCard, activeProg, data.strings.running || 'Resuming backup job…', activeStage );
			addTerminalLine( 'Resuming background job #' + activeId + '…', 'stage' );
			step( activeId, progressCard );
		}

		var cancelBtn = document.getElementById( 'backupino-cancel-job' );
		if ( cancelBtn && progressCard ) {
			cancelBtn.addEventListener( 'click', function () {
				var activeId = progressCard.getAttribute( 'data-active-job' ) || currentActiveJobId;
				if ( ! activeId ) {
					return;
				}
				if ( ! window.confirm( data.strings.cancelConfirm || 'Cancel this backup process?' ) ) {
					return;
				}
				isJobCancelled = true;
				cancelBtn.disabled = true;
				request( 'jobs/' + activeId + '/cancel', { method: 'POST' } )
					.then( function () {
						addTerminalLine( 'CANCELLED: Backup job #' + activeId + ' cancelled by user.', 'error' );
						progressBar( progressCard, 0, data.strings.cancelled || 'Job cancelled.', '', false, true );
						window.setTimeout( function () {
							window.location.reload();
						}, 1200 );
					} )
					.catch( function ( err ) {
						cancelBtn.disabled = false;
						isJobCancelled = false;
						alert( err.message );
					} );
			} );
		}

		var testButton = document.getElementById( 'backupino-test-storage' );
		if ( testButton ) {
			testButton.addEventListener( 'click', function () {
				var target = document.getElementById( 'backupino-storage-result' );
				setResult( target, data.strings.running );

				request( 'system/storage-test', { method: 'POST' } )
					.then( function ( response ) {
						setResult( target, response.message, false );
					} )
					.catch( function ( error ) {
						setResult( target, error.message, true );
					} );
			} );
		}

		var modal = document.getElementById( 'backupino-job-modal' );
		var modalClose = document.getElementById( 'backupino-modal-close' );
		var modalCloseBtn = document.getElementById( 'backupino-modal-close-btn' );
		var modalTitle = document.getElementById( 'backupino-modal-job-title' );
		var modalStatus = document.getElementById( 'backupino-modal-status' );
		var modalProgress = document.getElementById( 'backupino-modal-progress' );
		var modalDate = document.getElementById( 'backupino-modal-date' );
		var modalMessage = document.getElementById( 'backupino-modal-message' );
		var modalLogContent = document.getElementById( 'backupino-modal-log-content' );
		var modalPackageWrap = document.getElementById( 'backupino-modal-package-wrap' );
		var modalPkgName = document.getElementById( 'backupino-modal-package-name' );
		var modalPkgSize = document.getElementById( 'backupino-modal-package-size' );
		var modalZipLink = document.getElementById( 'backupino-modal-download-zip' );
		var modalInstallerLink = document.getElementById( 'backupino-modal-download-installer' );

		if ( modal && modal.parentElement !== document.body ) {
			document.body.appendChild( modal );
		}

		function closeModal() {
			if ( modal ) {
				modal.style.display = 'none';
			}
		}

		if ( modalClose ) { modalClose.addEventListener( 'click', closeModal ); }
		if ( modalCloseBtn ) { modalCloseBtn.addEventListener( 'click', closeModal ); }
		if ( modal ) {
			modal.addEventListener( 'click', function ( e ) {
				if ( e.target === modal ) {
					closeModal();
				}
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Escape' && modal.style.display !== 'none' ) {
					closeModal();
				}
			} );
		}

		document.addEventListener( 'click', function ( e ) {
			var triggerBtn = e.target.closest( '.backupino-job-log-trigger' );
			if ( triggerBtn ) {
				e.preventDefault();
				var jobId = triggerBtn.getAttribute( 'data-job-id' );
				if ( ! jobId || ! modal ) {
					return;
				}

				modal.style.display = 'flex';
				modalTitle.textContent = ( data.strings.jobDetails || 'Backup Job' ) + ' #' + jobId;
				modalStatus.textContent = '…';
				modalStatus.className = 'backupino-status';
				modalProgress.textContent = '…';
				modalDate.textContent = '…';
				modalMessage.textContent = '…';
				modalLogContent.textContent = 'Loading logs…';
				modalPackageWrap.style.display = 'none';

				request( 'jobs/' + jobId, { method: 'GET' } )
					.then( function ( job ) {
						modalStatus.textContent = job.statusText || job.status || '';
						modalStatus.className = 'backupino-status backupino-status--' + ( job.status || '' );
						modalProgress.textContent = ( job.progress || 0 ) + '%';
						modalDate.textContent = job.createdAt || '—';
						modalMessage.textContent = job.message || '—';

						if ( job.package && job.package.name ) {
							modalPackageWrap.style.display = 'block';
							modalPkgName.textContent = job.package.name;
							modalPkgSize.textContent = job.package.size || '';
							if ( job.package.archiveUrl ) {
								modalZipLink.href = job.package.archiveUrl;
								modalZipLink.style.display = 'inline-flex';
							} else {
								modalZipLink.style.display = 'none';
							}
							if ( job.package.installerUrl ) {
								modalInstallerLink.href = job.package.installerUrl;
								modalInstallerLink.style.display = 'inline-flex';
							} else {
								modalInstallerLink.style.display = 'none';
							}
						} else {
							modalPackageWrap.style.display = 'none';
						}

						if ( job.logs && job.logs.length > 0 ) {
							modalLogContent.textContent = job.logs.join( '\n' );
						} else {
							modalLogContent.textContent = 'No specific log records found for this job.';
						}
					} )
					.catch( function ( err ) {
						modalLogContent.textContent = 'Failed to load details: ' + err.message;
					} );
				return;
			}

			var downloadPairBtn = e.target.closest( '.backupino-download-pair' );
			if ( downloadPairBtn ) {
				e.preventDefault();
				if ( downloadPairBtn.classList.contains( 'is-downloading' ) ) {
					return;
				}

				var archiveUrl = downloadPairBtn.getAttribute( 'data-archive' );
				var installerUrl = downloadPairBtn.getAttribute( 'data-installer' );

				// Step 1: Immediately trigger installer download via hidden iframe
				if ( installerUrl ) {
					var f1 = document.createElement( 'iframe' );
					f1.style.display = 'none';
					f1.src = installerUrl;
					document.body.appendChild( f1 );
					window.setTimeout( function () {
						if ( f1.parentNode ) {
							f1.parentNode.removeChild( f1 );
						}
					}, 60000 );
				}

				// Step 2: Trigger archive download 800ms later via hidden iframe
				if ( archiveUrl ) {
					downloadPairBtn.classList.add( 'is-downloading' );
					downloadPairBtn.style.opacity = '0.5';

					window.setTimeout( function () {
						var f2 = document.createElement( 'iframe' );
						f2.style.display = 'none';
						f2.src = archiveUrl;
						document.body.appendChild( f2 );
						window.setTimeout( function () {
							if ( f2.parentNode ) {
								f2.parentNode.removeChild( f2 );
							}
						}, 60000 );

						downloadPairBtn.classList.remove( 'is-downloading' );
						downloadPairBtn.style.opacity = '1';
					}, 800 );
				}
				return;
			}

			var deleteBtn = e.target.closest( '.backupino-delete' );
			if ( deleteBtn ) {
				e.preventDefault();
				if ( ! window.confirm( data.strings.confirm ) ) {
					return;
				}
				request( 'jobs/' + deleteBtn.getAttribute( 'data-job' ), { method: 'DELETE' } ).then( function () {
					window.location.reload();
				} );
				return;
			}
		} );
	}

	function bindSettings() {
		var form = document.getElementById( 'backupino-settings' );

		if ( form ) {
			function collect( formElement ) {
				var payload = { archive: {}, runtime: {}, storage: {}, schedule: {}, installer: {}, notify: {}, advanced: {} };

				Array.prototype.forEach.call( formElement.querySelectorAll( 'input, select, textarea' ), function ( field ) {
					var path = field.name.split( '.' );
					var value;

					if ( field.type === 'checkbox' ) {
						value = field.checked;
					} else if ( field.type === 'number' ) {
						value = parseInt( field.value, 10 ) || 0;
					} else {
						value = field.value;
					}

					if ( path.length !== 2 ) {
						return;
					}

					if ( path[0] === 'archive' && path[1] === 'exclude_rules' ) {
						value = field.value.split( '\n' ).map( function ( line ) {
							return line.trim();
						} ).filter( Boolean );
						payload.archive.exclude_rules = value;
						return;
					}

					if ( path[0] === 'archive' && path[1] === 'ignore_larger_than_mb' ) {
						payload.archive.ignore_larger_than = value * 1024 * 1024;
						return;
					}

					if ( ! payload[ path[0] ] ) {
						payload[ path[0] ] = {};
					}

					payload[ path[0] ][ path[1] ] = value;
				} );

				return payload;
			}

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				var payload = collect( form );
				var target = document.getElementById( 'backupino-settings-result' );
				setResult( target, data.strings.running );

				request( 'settings', { method: 'POST', body: JSON.stringify( payload ) } )
					.then( function () {
						setResult( target, data.strings.saved, false );
					} )
					.catch( function ( error ) {
						setResult( target, error.message, true );
					} );
			} );

			var notifyButton = document.getElementById( 'backupino-notify-test' );
			if ( notifyButton ) {
				notifyButton.addEventListener( 'click', function () {
					var target = document.getElementById( 'backupino-notify-result' );
					setResult( target, data.strings.running );

					request( 'settings', { method: 'POST', body: JSON.stringify( collect( form ) ) } )
						.then( function () {
							return request( 'notify/test', { method: 'POST' } );
						} )
						.then( function () {
							setResult( target, data.strings.notified, false );
						} )
						.catch( function ( error ) {
							setResult( target, error.message, true );
						} );
				} );
			}

			Array.prototype.forEach.call( document.querySelectorAll( '.backupino-test-single-notify' ), function ( btn ) {
				btn.addEventListener( 'click', function () {
					var service = btn.getAttribute( 'data-service' );
					var target = document.getElementById( 'backupino-notify-' + service + '-result' );
					setResult( target, data.strings.running );

					request( 'settings', { method: 'POST', body: JSON.stringify( collect( form ) ) } )
						.then( function () {
							return request( 'notify/test', {
								method: 'POST',
								body: JSON.stringify( { service: service } )
							} );
						} )
						.then( function () {
							setResult( target, data.strings.notified, false );
						} )
						.catch( function ( error ) {
							setResult( target, error.message, true );
						} );
				} );
			} );
		}

		var testButton = document.getElementById( 'backupino-test-storage' );
		if ( testButton ) {
			testButton.addEventListener( 'click', function () {
				var target = document.getElementById( 'backupino-storage-result' );
				setResult( target, data.strings.running );

				request( 'system/storage-test', { method: 'POST' } )
					.then( function ( response ) {
						setResult( target, response.message, false );
					} )
					.catch( function ( error ) {
						setResult( target, error.message, true );
					} );
			} );
		}

		var refreshLogBtn = document.getElementById( 'backupino-refresh-log' );
		var logContainer = document.getElementById( 'backupino-system-log' );
		if ( refreshLogBtn && logContainer ) {
			refreshLogBtn.addEventListener( 'click', function () {
				refreshLogBtn.disabled = true;
				request( 'system', { method: 'GET' } )
					.then( function ( res ) {
						logContainer.innerHTML = '';
						var lines = [];
						if ( typeof res.log === 'string' ) {
							lines = res.log.trim() ? res.log.trim().split( '\n' ) : [];
						} else if ( Array.isArray( res.log ) ) {
							lines = res.log;
						}

						if ( lines.length > 0 ) {
							lines.forEach( function ( line ) {
								var div = document.createElement( 'div' );
								div.className = 'backupino-log-line';
								var mSpan = document.createElement( 'span' );
								mSpan.className = 'log-msg';
								mSpan.textContent = typeof line === 'string' ? line : ( line.message || JSON.stringify( line ) );
								div.appendChild( mSpan );
								logContainer.appendChild( div );
							} );
						} else {
							logContainer.innerHTML = '<div class="backupino-log-line init">No log entries found.</div>';
						}
					} )
					.catch( function ( err ) {
						logContainer.innerHTML = '<div class="backupino-log-line" style="color: #ef4444;">Failed to load logs: ' + err.message + '</div>';
					} )
					.then( function () {
						refreshLogBtn.disabled = false;
					} );
			} );
		}
	}

	function bindPermanentLink() {
		var button = document.getElementById( 'backupino-copy-link' );
		var field = document.getElementById( 'backupino-permalink' );
		var result = document.getElementById( 'backupino-copy-result' );

		if ( ! button || ! field ) {
			return;
		}

		button.addEventListener( 'click', function () {
			field.select();

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( field.value ).then( function () {
					setResult( result, data.strings.copied, false );
				} );
				return;
			}

			setResult( result, field.value, false );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		bindDashboard();
		bindSettings();
		bindPermanentLink();
	} );
}() );
