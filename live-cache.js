/* 
 * 
 */


function Live_Cache_Obj() {
	var self = this,
		ajaxurl = Live_Cache.ajaxurl, // Value originally set by localize script
		values = [],
		callbacks = [],
		timeStamp = 1;

	this.timer = setInterval(function () {
		Live_Cache.check();
	}, 6000);

	this.getValue = function (key) {
		// if it has been more than 60 sec by local clock
		self.check();
		return (typeof values[key] !== "undefined") ? values[key] : false;
	};

	this.setCallback = function (key, callback ) {
		if (typeof callbacks[key] == "undefined") {
			callbacks[key] = jQuery.Callbacks( "unique" );
		}
		callbacks[key].add(callback);
	};

	this.check = function () {
		jQuery.get(
			ajaxurl,
			{ "live_cache_check": timeStamp },
			function (data, s, resp) {
				var time = resp.getResponseHeader('Date').split(" ")[4];
				console.log(time);
				timeStamp = time.substr(0,2) + time.substr(3,2) + time.substr(6,1);
				jQuery.each(data, function(key, value) {
					if ( typeof callbacks[key] !== "undefined" && (typeof values[key] == "undefined" || values[key] !== value) ){
						callbacks[key].fire(value);
					}
				});
				values = data;
			},
			"json"
		);
	};
}

/*
 * Use our own system reset our timer if the refresh rate has been updated
 */
var Live_Cache = new Live_Cache_Obj();
Live_Cache.setCallback('refresh_rate', function (value) {
	clearInterval(Live_Cache.timer);
	Live_Cache.timer = setInterval(function () {
		Live_Cache.check();
	}, value * 1000);
});
