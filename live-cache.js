/*
 *
 */
(function (window, $, LC, undefined) {
	function Live_Cache_Obj() {
		var self = this,
				ajaxurl = LC.ajaxurl, // ajaxurl and auto_updates values originally set by localize script
				values = [],
				callbacks = [],
				timeStamp = 1,
				errs = 0,
				runCache = null;

		function getRefreshRate(rate) {
			rate = parseInt(rate, 10);
			return Math.max(rate, 60);
		}

		function formatTimestampEndpoint(int) {
			return ('000000' + parseInt(int, 10)).slice(-6).substr(0, 5);
		}

		function int_to_timestamp(int) {
			int %= 86400;
			var timestamp = [];
			timestamp[0] = ('00' + Math.floor(int / 3600)).slice(-2);
			timestamp[1] = ('00' + Math.floor((int % 3600) / 60)).slice(-2);
			timestamp[2] = ('00' + (int % 60)).slice(-2);
			return timestamp.join('');
		}

		this.auto_updates = LC.auto_updates;

		// schedule first check
		this.timer = setInterval(function () {
			self.check();
		}, 10000);

		// to customize what happens when a value is updated, set a callback for that key
		this.setCallback = function (key, callback) {
			if (undefined === callbacks[key]) {
				callbacks[key] = $.Callbacks("unique");
			}
			callbacks[key].add(callback);
		};

		this.check = function () {
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
								serverTime = Math.round((new Date(date)).getTime() / 1000);

						data.refresh_rate = getRefreshRate(data.refresh_rate || 0);

						// look only at the tens place of the second counter to refresh cache every ten seconds.
						timeStamp = formatTimestampEndpoint(int_to_timestamp(serverTime + data.refresh_rate));

						$.each(data, function (key, value) {
							if (undefined !== callbacks[key] && (undefined === values[key] || values[key] !== value)) {
								callbacks[key].fire(key, value); //pass key and value so that we can use the same callback for multiple keys
							}
						});

						values = data;
					},
					dataType: "json",
					error   : function () {
						callbacks['refresh_rate'].fire('refresh_rate', getRefreshRate() * ++errs);
					}
				});
			}
		};
	}

// initialize everything
	var liveCache = new Live_Cache_Obj();
	window.Live_Cache = liveCache;

	function liveCacheCallbackGenerator(lc) {
		return function (k, v) {
			$(lc.auto_updates[k]).html(v);
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


	/*
	 * Use our own system - reset our timer if the refresh rate has been updated
	 * Also a handy usage demo
	 */
	liveCache.setCallback('refresh_rate', function (key, value) {
		clearInterval(liveCache.timer);
		liveCache.timer = setInterval(function () {
			liveCache.check();
		}, value * 1000);
	});
}(this, this.jQuery, this.Live_Cache));
