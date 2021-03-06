/*
 *
 */
(function (window, $, LC, undefined) {
	function Live_Cache_Obj() {
		var self = this,
				ajaxurl = LC.ajaxurl, // ajaxurl and auto_updates values originally set by localize script
				values = {
					refresh_rate: 60 // Initialize to 60s here too
				},
				callbacks = [],
				timeStamp = 1,
				errs = 0,
				runCache = null;

		function getRefreshRate(rate) {
			rate = parseInt(rate, 10);
			// If somehow a non-integer can get through this check, I give up on JS forever
			if (isNaN(rate) || Infinity === rate || 'number' !== typeof rate) {
				rate = 60;
			}
			return Math.max(rate, 60);
		}

		function formatTimestampEndpoint( timestamp ) {
			return ( '000000' + parseInt( timestamp, 10 ) ).slice( -6 ).substr( 0, 5 );
		}

		function int_to_timestamp(intValue) {
			intValue %= 86400;
			var timestamp = [];
			timestamp[0] = ('00' + Math.floor(intValue / 3600)).slice(-2);
			timestamp[1] = ('00' + Math.floor((intValue % 3600) / 60)).slice(-2);
			timestamp[2] = ('00' + (intValue % 60)).slice(-2);
			return timestamp.join('');
		}

		this.auto_updates = LC.auto_updates;

		// schedule first check
		this.timer = setTimeout(function () {
			self.check();
		}, 10000);

		this.reschedule = function() {
			// If we've had less than three errors, continue to reschedule, otherwise just kill the live cache check
			if (errs < 3) {
				// Clear any existing timeout
				clearTimeout(self.timer);

				// Set a new timeout, for 60s (or longer) in the future
				self.timer = setTimeout(function() {
					self.check();
				}, 1000 * Math.max(60, getRefreshRate(values.refresh_rate)));
			}
		};

		// to customize what happens when a value is updated, set a callback for that key
		this.setCallback = function (key, callback) {
			if (undefined === callbacks[key]) {
				callbacks[key] = $.Callbacks("unique");
			}
			callbacks[key].add(callback);
		};

		this.check = function () {
			var self = this;
			if (undefined === runCache || null === runCache) {
				$.each(self.auto_updates, function () {
					var selector = "" + this;
					if (!runCache && $(selector).length) {
						runCache = true;
					}
				});
			}
			if (runCache) {
				$.ajax({
					url     : ajaxurl + '/live_cache_check/' + timeStamp + '/',
					success : function (data, s, resp) {
						var date = resp.getResponseHeader('Date'),
								serverTime = Math.round((new Date(date)).getTime() / 1000),
								lastModified = resp.getResponseHeader('Last-Modified'),
								lastModifiedTime;

						data.refresh_rate = getRefreshRate(data.refresh_rate || 0);

						// look only at the tens place of the second counter to refresh cache every ten seconds.
						timeStamp = formatTimestampEndpoint(int_to_timestamp(serverTime + data.refresh_rate));

						if (lastModified && /\/live_cache_check\/1\/?$/.test(this.url)) {
							lastModifiedTime = Math.round((new Date(lastModified)).getTime() / 1000);
							if (lastModifiedTime + data.refresh_rate + 10 < serverTime) {
								timeStamp = formatTimestampEndpoint(int_to_timestamp(serverTime));
								self.check();
								return;
							}
						}

						$.each(data, function (key, value) {
							if (undefined !== callbacks[key] && (undefined === values[key] || values[key] !== value)) {
								callbacks[key].fire(key, value); //pass key and value so that we can use the same callback for multiple keys
							}
						});

						values = data;
					},
					dataType: "json",
					error   : function () {
						// On an error, increase the refresh rate a little
						values.refresh_rate = getRefreshRate(60) * ++errs;
					}
				})
					.always(self.reschedule);
			}
		};
	}

// initialize everything
	var liveCache = new Live_Cache_Obj();
	window.Live_Cache = liveCache;

	function liveCacheCallbackGenerator(lc) {
		return function (k, v) {
			$(lc.auto_updates[k]).text(v);
		};
	}

	/*
	 * parse requests to directly output to page
	 */
	for (var key in liveCache.auto_updates) {
		if (liveCache.auto_updates.hasOwnProperty(key)) {
			liveCache.setCallback(key, liveCacheCallbackGenerator(liveCache));
		}
	}

}(this, this.jQuery, this.Live_Cache));
