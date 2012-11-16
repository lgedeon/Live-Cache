/* 
 * 
 */


var Live_Cache = {
	ajaxurl: Live_Cache.ajaxurl, /*value originally set by localize script*/
	values: [],
	callbacks: [],
	timeStamp: 1,
	timer: setInterval(function(){Live_Cache.check();},6000),
	getValue: function (key) {
		// if it has been more than 60 sec by local clock
			this.check();
		return (typeof this.values[key]!=="undefined") ? this.values[key] : false;
	},
	setCallback: function (key, callback ) {
		if (typeof this.callbacks[key]=="undefined") 
			this.callbacks[key] = jQuery.Callbacks( "unique" );
		this.callbacks[key].add(callback);
	},
	check: function () {
		jQuery.get(
			Live_Cache.ajaxurl,
			{ "live_cache_check": this.timeStamp },
			function(data,s,resp){
				var time = resp.getResponseHeader('Date').split(" ")[4];
				console.log(time);
				Live_Cache.timeStamp = time.substr(0,2)+time.substr(3,2)+time.substr(6,1);
				jQuery.each(data, function(key, value) {
					if ( typeof Live_Cache.callbacks[key]!=="undefined" && (typeof Live_Cache.values[key]=="undefined" || Live_Cache.values[key]!==value) ){
						Live_Cache.callbacks[key].fire(value);
					}
				});
				Live_Cache.values = data;
			},
			"json"
		);
	}
}

/*
 * Use our own system reset our timer if the refresh rate has been updated
 */
Live_Cache.setCallback('refresh_rate', function(value){
	clearInterval(Live_Cache.timer);
	Live_Cache.timer=setInterval(function(){Live_Cache.check();},value*1000);
});
