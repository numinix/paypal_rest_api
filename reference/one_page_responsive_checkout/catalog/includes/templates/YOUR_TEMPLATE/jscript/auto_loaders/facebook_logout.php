<script type="text/javascript" src="http://connect.facebook.net/en_US/all.js"></script>
<script type="text/javascript"><!--//
  window.fbAsyncInit = function() {
    jQuery('html').append('<div id="fb-root"></div>');
    FB.init({ 
      appId: '<?php echo FACEBOOK_OPEN_GRAPH_APPID; ?>', 
      cookie:true, 
      status:true, 
      xfbml:true 
    });
	FB.getLoginStatus(function(response) {
	  if (response.status === 'connected') {
		// the user is logged in and has authenticated your
		// app, and response.authResponse supplies
		// the user's ID, a valid access token, a signed
		// request, and the time the access token 
		// and signed request each expire
		var uid = response.authResponse.userID;
		var accessToken = response.authResponse.accessToken;
        //if we do have a non-null response.session, call FB.logout(),
        //the JS method will log the user out of Facebook and remove any authorization cookies
        FB.logout(response.authResponse);
	  }
	});
  }
//--></script>