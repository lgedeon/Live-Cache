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
				minRefresh = 60,
				errs = 0,
				runCache = null;

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
						var time = resp.getResponseHeader('Date').split(" ")[4];
						// check our special variable refresh_rate - if it is not set, we need to set a default
						if (undefined === data['refresh_rate'] || data['refresh_rate'] < minRefresh) {
							data['refresh_rate'] = minRefresh * 2;
						}
						// look only at the tens place of the second counter to refresh cache every ten seconds.
						timeStamp = time.substr(0, 2) + time.substr(3, 2) + time.substr(6, 1);
						$.each(data, function (key, value) {
							if (undefined !== callbacks[key] && (undefined === values[key] || values[key] !== value)) {
								callbacks[key].fire(key, value); //pass key and value so that we can use the same callback for multiple keys
							}
						});
						values = data;
					},
					dataType: "json",
					error   : function () {
						callbacks['refresh_rate'].fire('refresh_rate', minRefresh * ++errs);
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
