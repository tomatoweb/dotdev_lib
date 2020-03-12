
jQuery.noConflict(); // reinitialize global variables (e.g. $), avoiding conflict with other .js

/*
* Prevent any jQuery code from running before the document is finished loading (is ready)
* 3 synonym methods:
*
* $(document).ready(function(){ -- W3C prefered method --
*    ..code..
* });
*
* $(function(){
*     ..code..
* });
*
* (function($){
*     ..code..
* })(jquery);
*/
(function($){

	var app = angular.module('app', ['ngSanitize', 'ui.select','ui.bootstrap', 'perfect_scrollbar', 'ngBootbox', 'infinite-scroll']);

    // process infinite-scroll events a maximum of once every x milliseconds because scroll events can be triggered very frequently, which can hurt performance and make scrolling appear jerky
    angular.module('infinite-scroll').value('THROTTLE_MILLISECONDS', 100);

    app.config(function($locationProvider) {
        $locationProvider.html5Mode({

            // enable html5
            enabled: true,

            // avoid use of <base href="">. But do not forget to add target="_self" in the <a></a> tag. for example: <a href="<?= $this->us_url('?sesreset=1') ?>" target="_self">Ausloggen</a>
            requireBase: false
        });
    });

    // Format date
    app.filter('datetime', function ($filter) {

        return function(input){

            if(input == null){
                return "";
            }

            input = input.replace(/(.+) (.+)/, "$1T$2Z"); // for Firefox and Safari

            var _date = $filter('date')(new Date(input), 'dd.MM.yyyy - HH:mm:ss');

            return _date.toUpperCase();

         };
    });

    // test if an object is empty
    app.filter('isEmpty', function ($filter) {
        var bar;
        return function (obj) {
            for (bar in obj) {
                if (obj.hasOwnProperty(bar)) {
                    return false;
                }
            }
            return true;
        };
    });

    app.filter('propsFilter', function() {
        return function(items, props) {
            var out = [];
            if (angular.isArray(items)) {
                items.forEach(function(item) {
                    var itemMatches = false;
                    var keys = Object.keys(props);
                    for (var i = 0; i < keys.length; i++) {
                        var prop = keys[i];
                         var text = props[prop].toString().toLowerCase();
                        if (item[prop].toString().toLowerCase().indexOf(text) !== -1) {
                            itemMatches = true;
                            break;
                        }
                    }
                    if (itemMatches) {
                        out.push(item);
                    }
                });
            } else {
                // Let the output be the input untouched
                out = items;
            }
            return out;
        }
    });

    // directive to automaticly resize a textarea to always fit the height of its content
    app.directive('textareaFit', ['$log', function ($log) {

        var copyCssStyles = function (elSrc, elDest) {
            var stylesToCopy = [
                'width',
                'font-family',
                'font-size',
                'line-height',
                'min-height',
                'padding'
            ],
            destStyles = {};

            angular.forEach(stylesToCopy, function (style) {
                destStyles[style] = elSrc.css(style);
            });

            elDest.css(destStyles);
        };

        return {
            restrict: 'A',
            link : function ($scope, $element) {
                if (!angular.isFunction($element.height)) {
                    $log.error('textareaFit directive only works when jQuery is loaded');
                }
                else if (!$element.is('textarea')) {
                    $log.info('textareaFit directive only works for elements of type "textarea"');
                }
                else {
                    var elClone = angular.element('<div>'),
                    setEqualHeight = function () {
                        var curText = $element.val();
                        if (/\n$/.test(curText)) {
                            curText += ' ';
                        }
                        copyCssStyles($element, elClone);
                        elClone.text(curText);
                        $element.height(elClone.height());
                    };

                    elClone
                        .hide()
                        .css({
                            'white-space': 'pre-wrap',
                            'word-wrap' : 'break-word'
                        });
                    $element.parent().append(elClone);
                    $element.css('overflow', 'hidden');

                    $scope.$watch(function () {
                        return $element.val();
                    }, setEqualHeight);

                    $scope.$watch(function () {
                        return $element.width();
                    }, setEqualHeight);

                    $scope.$on('destroy', function () {
                      elClone.remove();
                      elClone = null;
                    });
                }
            }
        };
    }]);

	app.service('ajaxService', [ '$http', function($http){
	   return {
	            fn: function(url, data, callback){

	                    $http({
	                      method: 'POST',
	                      url: url,
	                      data: data
	                      })
	                    .then(function successCallback(response) {
	                            callback(response);
	                            },
	                          function errorCallback(response) { // called asynchronously if an error occurs or server returns response with an error status.
	                            callback(response);
	                            }
	                          );
	                }
	   }
	}]);

    app.service('anchorSmoothScroll', function(){

        this.scrollTo = function(eID) {

            // This scrolling function
            // is from http://www.itnewb.com/tutorial/Creating-the-Smooth-Scroll-Effect-with-JavaScript

            var startY = currentYPosition();
            var stopY = elmYPosition(eID);
            var distance = stopY > startY ? stopY - startY : startY - stopY;
            if (distance < 100) {
                scrollTo(0, stopY); return;
            }
            var speed = Math.round(distance / 100);
            if (speed >= 20) speed = 20;
            var step = Math.round(distance / 25);
            var leapY = stopY > startY ? startY + step : startY - step;
            var timer = 0;
            if (stopY > startY) {
                for ( var i=startY; i<stopY; i+=step ) {
                    setTimeout("window.scrollTo(0, "+leapY+")", timer * speed);
                    leapY += step; if (leapY > stopY) leapY = stopY; timer++;
                } return;
            }
            for ( var i=startY; i>stopY; i-=step ) {
                setTimeout("window.scrollTo(0, "+leapY+")", timer * speed);
                leapY -= step; if (leapY < stopY) leapY = stopY; timer++;
            }

            function currentYPosition() {
                // Firefox, Chrome, Opera, Safari
                if (self.pageYOffset) return self.pageYOffset;
                // Internet Explorer 6 - standards mode
                if (document.documentElement && document.documentElement.scrollTop)
                    return document.documentElement.scrollTop;
                // Internet Explorer 6, 7 and 8
                if (document.body.scrollTop) return document.body.scrollTop;
                return 0;
            }

            function elmYPosition(eID) {
                var elm = document.getElementById(eID);
                var y = elm.offsetTop;
                var node = elm;
                while (node.offsetParent && node.offsetParent != document.body) {
                    node = node.offsetParent;
                    y += node.offsetTop;
                } return y;
            }

        };

    });

    // waiting for ngRepeat to reach last element
    app.directive('myRepeatDirective', function() {
      return function(scope, element, attrs) {

        // wait till last iteration of ng-repeat to do something on parent (myMainDirective)
        if (scope.$last){

            // emit some event that will be listened by directive myMainDirective
            scope.$emit('LastElem');
        }
      };
    });

    // automatic scroll <div id="chat-table"> to bottom. Need myRepeatDirective to wait for ng-repeat last element reached.
    app.directive('myMainDirective', function() {
      return function(scope, element, attrs) {
        //angular.element(element).css('border','5px solid red');

        // wait till last iteration of ng-repeat (myRepeatDirective) to do something on parent (myMainDirective)
        scope.$on('LastElem', function(event){
            var scrollHeight = element.prop('scrollHeight'); // number of children elements
            var height = 100; // height of a child element
            element.animate({scrollTop: scrollHeight*height}, 100); // 100 = fast, 1000 = slow
        });
      };
    });

	app.controller('appCtrl',
		// The $ prefix denotes a variable, parameter, property, or method that belongs to the core of Angular.
		['$scope', '$filter', '$compile', '$window', '$http', 'ajaxService', '$ngBootbox',  '$anchorScroll', '$location', '$timeout', 'anchorSmoothScroll', function($scope, $filter, $compile, $window, $http, ajaxService, $ngBootbox, $anchorScroll, $location, $timeout, anchorSmoothScroll){

        /*
        * IMPORTANT: $location service need target="_self" in href elements in views
        */

        // TEMP DEMO
        $scope.msisdnFilled = 15254176833; // default MSISDN input value for demo mode

        // initialize scope variables
        $scope.credits = {count: 0};
        $scope.unreadMessages = {length: 0};
        $scope.thumb  = true;
        $scope.gender = [
            {gender:"m"},
            {gender:"f"}
        ];
        $scope.orientation = [
            {orientation:"m"},
            {orientation:"f"},
            {orientation:"b"}
        ];
        $scope.textAllCountries = {
            de : {
                10  : 'Beschreibung',
                20  : 'Löschen',
                30  : 'Als Profilbild',
                40  : 'Profilaktualisierung...',
                50  : 'Das Profil wurde aktualisiert.',
                60  : 'Geben Sie einen Benutzernamen ein.',
                70  : 'Kostenlos anmelden',
                80  : 'Impressum',
                90  : 'Kontakt',
                100 : 'AGB',
                110 : 'Neu hier? Einfach Handynummer eintragen und ',
                120 : 'kostenlos',
                130 : ' TAN anfordern',
                140 : 'Deine Handynummer',
                145 : 'z.B.',
                150 : 'Kostenlose TAN anfordern',
                160 : '<div class="bottom10 inline-block">Ungültige Handynummer&nbsp;&nbsp;</div><div class="inline-block bottom10">(z.B. 49123456789)</div>',
                161 : 'Ungültige Eingabe.<br>Erlaubt sind: (a-z, A-Z, -, _, Leerzeichen)',
                170 : 'und',
                180 : 'ungelesene Nachrichten',
                190 : 'Du hast noch keinen Chat gestartet. Schreibe jetzt ein Girl an!',
                200 : 'Hallo! Fragt mich alles!',
                210 : 'Du hast noch kein Profil erstellt. Wähle Deinen Benutzernamen',
                220 : 'zwischen 3 - 20 Zeichen',
                230 : 'Mache ich später',
                240 : 'Dein persönlicher TAN wird nun versendet.',
                250 : 'Herzlich Willkommen bei '+$scope.us.title+'!<br>Viel Spaß beim Chatten!',
                260 : $scope.us.title+' gibt es auch kostenlos für Android',
                270 : 'Mein Profil',
                280 : 'Mehr Profile'
            },
            en : {
                10  : 'Description',
                20  : 'Delete',
                30  : 'Set as front image',
                40  : 'Updating profile...',
                50  : 'Profile updated.',
                60  : 'Please, enter a username.',
                70  : 'Register for free',
                80  : 'Imprint',
                90  : 'Contact',
                100 : 'Terms and conditions',
                110 : 'New here? Simply enter your mobile number ',
                120 : 'for Free',
                130 : ' TAN request',
                140 : 'Your mobile number',
                145 : 'e.g.',
                150 : 'Free TAN request',
                160 : 'Invalid mobile number (e.g. 49123456789)',
                161 : 'Invalid username',
                170 : 'and',
                180 : 'unread messages',
                190 : 'You haven not started chatting yet.',
                200 : 'Hi! Ask Me Anything!',
                210 : 'You haven\'t created a profile yet. Choose your username',
                220 : 'your username',
                230 : 'Maybe later',
                240 : 'Your personal TAN will now be sent.',
                250 : 'Welcome to '+$scope.us.title+'!',
                260 : $scope.us.title+' gibt es auch kostenlos für Android',
                270 : 'My profile',
                280 : 'More profiles'
            },
            cs : {
                10  : 'Description',
                20  : 'Delete',
                30  : 'Set as front image',
                40  : 'Updating profile...',
                50  : 'Profile updated.',
                60  : 'Please, enter a username.',
                70  : 'Register for free',
                80  : 'Imprint',
                90  : 'Contact',
                100 : 'Terms and conditions',
                110 : 'New here? Simply enter your mobile number ',
                120 : 'for Free',
                130 : ' TAN request',
                140 : 'Your mobile number',
                145 : 'e.g.',
                150 : 'Free TAN request',
                160 : 'Invalid mobile number (e.g. 49123456789)',
                161 : 'Invalid username',
                170 : 'and',
                180 : 'unread messages',
                190 : 'You haven not started chatting yet.',
                200 : 'Hi! Ask Me Anything!',
                210 : 'You haven\'t created a profile yet. Choose your username',
                220 : 'your username',
                230 : 'Maybe later',
                240 : 'Your personal TAN will now be sent.',
                250 : 'Welcome to '+$scope.us.title+'!',
                260 : $scope.us.title+' gibt es auch kostenlos für Android',
                270 : 'My profile',
                280 : 'More profiles'

            },
            hu : {
                10  : 'Description',
                20  : 'Delete',
                30  : 'Set as front image',
                40  : 'Updating profile...',
                50  : 'Profile updated.',
                60  : 'Please, enter a username.',
                70  : 'Register for free',
                80  : 'Imprint',
                90  : 'Contact',
                100 : 'Terms and conditions',
                110 : 'New here? Simply enter your mobile number ',
                120 : 'for Free',
                130 : ' TAN request',
                140 : 'Your mobile number',
                145 : 'e.g.',
                150 : 'Free TAN request',
                160 : 'Invalid mobile number (e.g. 49123456789)',
                161 : 'Invalid username',
                170 : 'and',
                180 : 'unread messages',
                190 : 'You haven not started chatting yet.',
                200 : 'Hi! Ask Me Anything!',
                210 : 'You haven\'t created a profile yet. Choose your username',
                220 : 'your username',
                230 : 'Maybe later',
                240 : 'Your personal TAN will now be sent.',
                250 : 'Welcome to '+$scope.us.title+'!',
                260 : $scope.us.title+' gibt es auch kostenlos für Android',
                270 : 'My profile',
                280 : 'More profiles'
            },
        };
        $scope.text = $scope.textAllCountries[$scope.us.lang];

        $scope.year = (new Date()).getFullYear();

        // set default prefix int
        $scope.countrySelection = [];
        $scope.countrySelection['selected'] = {"countryID": "1"};

        // hide message box to user
        $("#alert").hide();

        $scope.doShuffle = function() {
            shuffleArray($scope.profiles);
        };

        // -> Fisher–Yates shuffle algorithm
        var shuffleArray = function(array) {
            var m = array.length, t, i;

            // While there remain elements to shuffle
            while (m) {
              // Pick a remaining element…
              i = Math.floor(Math.random() * m--);

              // And swap it with the current element.
              t = array[m];
              array[m] = array[i];
              array[i] = t;
            }

            return array;
        };

        // hash function
        $scope.encryptPassword = function(txt_string){

            // encryption tool
            var tool = {
                utf8_encode : function(argString){
                    //  discuss at: http://phpjs.org/functions/utf8_encode/
                    if(argString === null || typeof argString === 'undefined'){
                        return '';
                    }
                    var string = (argString + ''); // .replace(/\r\n/g, "\n").replace(/\r/g, "\n");
                    var utftext = '', start, end, stringl = 0, n, c1, enc, c2;
                    start = end = 0;
                    stringl = string.length;
                    for(n = 0; n < stringl; n++){
                        c1 = string.charCodeAt(n);
                        enc = null;
                        if(c1 < 128){
                            end++;
                        }
                        else if(c1 > 127 && c1 < 2048){
                            enc = String.fromCharCode((c1 >> 6) | 192, (c1 & 63) | 128);
                        }
                        else if((c1 & 0xF800) != 0xD800){
                            enc = String.fromCharCode((c1 >> 12) | 224, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128);
                        }
                        else{ // surrogate pairs
                            if((c1 & 0xFC00) != 0xD800){
                                throw new RangeError('Unmatched trail surrogate at ' + n);
                            }
                            c2 = string.charCodeAt(++n);
                            if((c2 & 0xFC00) != 0xDC00){
                                throw new RangeError('Unmatched lead surrogate at ' + (n - 1));
                            }
                            c1 = ((c1 & 0x3FF) << 10) + (c2 & 0x3FF) + 0x10000;
                            enc = String.fromCharCode((c1 >> 18) | 240, ((c1 >> 12) & 63) | 128, ((c1 >> 6) & 63) | 128, (c1 & 63) | 128);
                        }
                        if(enc !== null){
                            if(end > start){
                                utftext += string.slice(start, end);
                            }
                            utftext += enc;
                            start = end = n + 1;
                        }
                    }
                    if(end > start){
                        utftext += string.slice(start, stringl);
                    }
                    return utftext;
                },
                sha1 : function(str){
                    //  discuss at: http://phpjs.org/functions/sha1/
                    var rotate_left = function(n, s) {
                        var t4 = (n << s) | (n >>> (32 - s));
                        return t4;
                    };
                    var cvt_hex = function(val){
                        var str = '', i, v;
                        for(i = 7; i >= 0; i--){
                            v = (val >>> (i * 4)) & 0x0f;
                            str += v.toString(16);
                        }
                        return str;
                    };
                    var blockstart,
                    i, j,
                    W = new Array(80),
                    H0 = 0x67452301,
                    H1 = 0xEFCDAB89,
                    H2 = 0x98BADCFE,
                    H3 = 0x10325476,
                    H4 = 0xC3D2E1F0,
                    A, B, C, D, E,
                    temp;
                    str = this.utf8_encode(str);
                    var str_len = str.length;
                    var word_array = [];
                    for(i = 0; i < str_len - 3; i += 4){
                        j = str.charCodeAt(i) << 24 | str.charCodeAt(i + 1) << 16 | str.charCodeAt(i + 2) << 8 | str.charCodeAt(i + 3);
                        word_array.push(j);
                    }
                    switch(str_len % 4){
                        case 0:
                            i = 0x080000000;
                            break;
                        case 1:
                            i = str.charCodeAt(str_len - 1) << 24 | 0x0800000;
                            break;
                        case 2:
                            i = str.charCodeAt(str_len - 2) << 24 | str.charCodeAt(str_len - 1) << 16 | 0x08000;
                            break;
                        case 3:
                            i = str.charCodeAt(str_len - 3) << 24 | str.charCodeAt(str_len - 2) << 16 | str.charCodeAt(str_len - 1) << 8 | 0x80;
                            break;
                    }
                    word_array.push(i);
                    while((word_array.length % 16) != 14){
                        word_array.push(0);
                    }
                    word_array.push(str_len >>> 29);
                    word_array.push((str_len << 3) & 0x0ffffffff);
                    for(blockstart = 0; blockstart < word_array.length; blockstart += 16){
                        for(i = 0; i < 16; i++){
                            W[i] = word_array[blockstart + i];
                        }
                        for(i = 16; i <= 79; i++){
                            W[i] = rotate_left(W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16], 1);
                        }
                        A = H0;
                        B = H1;
                        C = H2;
                        D = H3;
                        E = H4;
                        for(i = 0; i <= 19; i++){
                            temp = (rotate_left(A, 5) + ((B & C) | (~B & D)) + E + W[i] + 0x5A827999) & 0x0ffffffff;
                            E = D;
                            D = C;
                            C = rotate_left(B, 30);
                            B = A;
                            A = temp;
                        }
                        for(i = 20; i <= 39; i++){
                            temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0x6ED9EBA1) & 0x0ffffffff;
                            E = D;
                            D = C;
                            C = rotate_left(B, 30);
                            B = A;
                            A = temp;
                        }
                        for(i = 40; i <= 59; i++){
                            temp = (rotate_left(A, 5) + ((B & C) | (B & D) | (C & D)) + E + W[i] + 0x8F1BBCDC) & 0x0ffffffff;
                            E = D;
                            D = C;
                            C = rotate_left(B, 30);
                            B = A;
                            A = temp;
                        }
                        for(i = 60; i <= 79; i++){
                            temp = (rotate_left(A, 5) + (B ^ C ^ D) + E + W[i] + 0xCA62C1D6) & 0x0ffffffff;
                            E = D;
                            D = C;
                            C = rotate_left(B, 30);
                            B = A;
                            A = temp;
                        }
                        H0 = (H0 + A) & 0x0ffffffff;
                        H1 = (H1 + B) & 0x0ffffffff;
                        H2 = (H2 + C) & 0x0ffffffff;
                        H3 = (H3 + D) & 0x0ffffffff;
                        H4 = (H4 + E) & 0x0ffffffff;
                    }
                    temp = cvt_hex(H0) + cvt_hex(H1) + cvt_hex(H2) + cvt_hex(H3) + cvt_hex(H4);
                    return temp.toLowerCase();
                }
            }

            return tool.sha1(txt_string);
        };

        // prompt for password in beta version of http://{domain}/new
        $scope.checkPwd = function(){
            bootbox.prompt({
                size: "small",
                title: "Please enter beta version password",
                inputType: 'password',
                closeButton: false,
                buttons: {
                    confirm: {
                        label: 'OK',
                        className: 'btn-success btn-primary' // primary gives focus on enter pressed
                    },
                    cancel: {
                        label: '',
                        className: 'hide'
                    }
                },
                callback: function (password){
                    if($scope.encryptPassword(password) == "9299b2a61bb26c08e468354079cadbc5ca35f664"){
                        console.log("beta version password ok.");
                    }else{
                        window.location.href = $scope.us.sameurl;
                    }
                }
            });
        };

        // bootbox: user has no chat started.
        $scope.checkChats = function(){

            // No chats: display bootbox
            if($scope.chatProfiles.length == 0){

                // get url from thumb from first random profile to display in head of bootbox
                url = $scope.us.resourceUrl+'/p'+$scope.profiles[0].profileID+'/'+ $scope.profiles[0].thumbName;

                // display boobtbox
                bootbox.alert({
                    message: "<div class=''><img src='" + url + "' style='border-radius:80px;height:120px;float:left'><div>" + $scope.text[190] + "</div></div>",
                    size: 'large',
                    className: 'center',
                    backdrop: true,             // close when outside click or escape
                    onEscape: true,              // close when outside click or escape (both options are needed for .dialog, but not for alert, confirm or prompt)
                })
                .find('.modal-content').addClass('bootbox-checkchats')
                .find('.modal-footer').addClass('bootbox-checkchats-footer')
                .find('.btn').removeClass("btn-primary").addClass("button-"+$scope.us.scheme).addClass('hide');
            }
        };


        $scope.validateUsername = function(e){

            if(typeof $scope.username !== 'undefined'){

                // clear error message
                $('#username').removeClass('error');
                $('#name-error').html('');

                 // Validate username (allow undescore, hyphen, white space)
                 if ( !$("#username").val() || ! /^[a-zA-Z0-9_-\s]{3,20}$/.test($("#username").val()) ) {
                        $('#username').addClass('error');
                        $('#name-error').html($scope.text[161]);
                        e.preventDefault();
                }

                else{

                    // update user profile
                    $scope.updateUserProfile('name', 'username');

                    // remove dropbox and its frame
                    $('#username-box').remove();
                    $('.modal-backdrop').remove();

                    // switch views
                    $scope.switchViews.showChat = false;
                    $scope.switchViews.showChats = false;
                    $scope.switchViews.showProfiles = false;
                    $scope.switchViews.showUser = true;


                }
            }
        };

        // bootbox: user has no profile.
        $scope.checkUserProfile = function(){

            // User has no profile: display bootbox
            if($filter('isEmpty')($scope.userProfile)){

                // A random fake-profile thumbnail URL
                var url = $scope.us.resourceUrl+'/p'+$scope.profiles[0].profileID+'/'+ $scope.profiles[0].thumbName;

                // prepare bootbox data for angularjs compilation
                var tplCrop =
                    "<div><img src='" + url + "' style='border-radius:80px;height:120px;'><br><div>" + $scope.text[210] + "</div></div>" +
                    '<div class="row">  ' +
                    '<form class="center form-horizontal"> ' +
                    '<div class="form-group"> ' +
                    '<div class=""><br><div id="name-error" class="red-error"></div>' +
                    '<input id="username" name="username" ng-model="username" type="text" placeholder="'+ $scope.text[220] +'" class="form-control input-md"> ' +
                    '</div></div>' +
                    '<button data-bb-handler="success" type="button" class="btn grow button-flirddy right-40" ng-disabled="!username" ng-click="validateUsername($event)">OK</button>' +
                    '<button data-bb-handler="cancel" type="button" class="btn btn-later left-35" ng-click="closeUsernameModal()">'+$scope.text[230]+'</button>' +
                    '</form></div>';
                var template = angular.element(tplCrop);

                // compile the text to html
                var linkFn = $compile(template);
                var html= linkFn($scope);

                bootbox.dialog({
                    title: "",
                    message: html,
                    size: 'small',
                    className: 'center',
                    backdrop: true,             // close when outside click or escape
                    onEscape: true,              // close when outside click or escape (both options are needed for .dialog, but not for alert, confirm or prompt)
                    callback: function () {}
                })
                .attr("id", "username-box")
                .find('.modal-content').css({'background-color': 'rgba(16,17,18,0.8)', 'font-weight' : 'bold', color: 'white', 'font-size': '1.2em', 'border-radius': '25px'} )
                .find('.modal-footer').css({'border': 'none'} )
                .find('.btn-danger').removeClass("btn-danger").addClass("btn-later allo2")
                .siblings('.btn-success').removeClass("btn-success").addClass("right-40 button-"+$scope.us.scheme).attr( "ng-disabled", "!username" );
            }
        };

        $scope.closeUsernameModal = function(){

            // remove dropbox and its frame
            $('#username-box').remove();
            $('.modal-backdrop').remove();
        };

        // prompt password for    http(s)://{domain}/new
        if (/\/new/.test($scope.us.sameurl)) {

            // only at first visit or if session is timed out
            if(!$scope.us.auth){
                //$scope.checkPwd();
            }
        }

        // views switch
        $scope.switchViews = {
            showProfiles    : true,
            showChats       : false,
            showChat        : false,
            showPics        : false,
            showUser        : false,
            showStats       : false
        };
        $scope.switchStatsViews = {
            showTanUsers       : true
        };

        /* Get user's Chats */
        $scope.getChats = function(){

            ajaxService.fn(

           	    $scope.us.ajaxurl,      		    // URL

            	{'msisdn':$scope.us.msisdn},     // Parameters

            	function (response){         		      // Callback function

            		if(response.status == 200){

                        $scope.chatProfiles = response.data;
                    }

                    else {

                        console.log('could not load Chats for msisdn ' + $scope.us.msisdn);

                    }
                }
            );
        };

        $scope.getChat = function(profileID, profileName, profileImageName, profileThumb, e){

            // save profile in scope
            $scope.profileID = profileID;
            $scope.profileName = profileName;
            $scope.profileImageName = profileImageName;
            $scope.profileThumb = profileThumb;

            // prepare ajax data
            var ajaxData = {
                'getChat': {
                    'profileID': $scope.profileID,
                    'mobileID'   : $scope.us.mobileID
                    }
            };

	        ajaxService.fn(
	       	    $scope.us.ajaxurl,                 // URL
	        	ajaxData,    // Parameters
	        	function (response){               // Callback function
	        		if(response.status == 200){
	            	    $scope.chat = response.data.reverse();

                        // empty chat: set default welkom message
                        if($scope.chat.length == 0){
                            $scope.chat[0] = {
                                createTime: new Date().toISOString(), // OK for Safari browser
                                from: 2,
                                read: 0,
                                text: $scope.text[200]
                            };
                        }

                        // resize <div id="chat-table">
                        if($scope.chat.length > 2){
                            if($scope.us.sms){
                                $('#chat-table').height(426);
                            }
                            else{
                                $('#chat-table').height(504);
                            }
                            $('.hidden-opt').hide();
                        } else{
                            if($scope.us.sms){
                                $('#chat-table').height(234);
                            }
                            else{
                                $('#chat-table').height(311);
                            }
                            $('.hidden-opt').show();
                        }

                        // scroll to top
                        //$scope.scrollTo('top-anchor');

                        // get images of selected profile
                        $scope.getImages($scope.profileID);

                        // reset gallery selected image
                        $scope.selectedImage = false;

                        // resize font of profileName text to fit container
                        if($scope.profileName.length > 10){
                            $('#profile-name').attr('style', 'font-size:30px !important; margin-top:3% !important');
                        }
                        else {
                            $('#profile-name').attr('style', 'font-size:50px !important; margin-top:-2% !important');
                        }

	         	    }
                    // ajax response NOK
	         	    else{
                        console.log('chat could not be loaded.')
	         	    }
	            }
	        );
        };

        $scope.liveChatClicked = function(index){

            // not auth
            if(!$scope.us.auth){

                // open login modal
                $('#elegantModalForm').modal('show');

                // Give focus to MSISDN input
                $scope.giveFocus('msisdn');
            }
            else{

                // open chat
                $scope.getChat($scope.profiles[index].profileID, $scope.profiles[index].profileName, $scope.profiles[index].imageName, $scope.profiles[index].thumbName);

                $scope.switchViews.showChat = true;
                $scope.switchViews.showChats = false;
                $scope.switchViews.showProfiles = false;
                $scope.switchViews.showUser = false;

                $scope.scrollTo('top-anchor');
            }
        };

        $scope.getImages = function(profileID, profileName, e){

	        ajaxService.fn(
	       	    $scope.us.ajaxurl,     // URL
	        	{'images':$scope.profileID}, 		// Parameters
	        	function (response){             // Callback function
	        		if(response.status == 200){
	            	    $scope.imgs = response.data;

                        // wait and update perfect-scrollbar after ajax request
                        setTimeout(function(){
                            $('#profile-gallery').perfectScrollbar("update");
                        }, 500);

                        // remove thumbs and chat images
                        for (var i = $scope.imgs.length - 1; i >= 0; i--) {
                            if ($scope.imgs[i].moderator != 0) {
                                $scope.imgs.splice(i, 1);
                            }
                        }


                        //console.log($scope.imgs);
	         	    }
	         	    else{
	         	    }
	            }
	        );
        };

        $scope.getUser = function (mobileID) {

            ajaxService.fn(
                $scope.us.ajaxurl,
                { 'mobileID': mobileID },
                function (response) {

                    if(response.status == 200){

                        $scope.userProfile = response.data;

                        // this will hide burger options button on default profile image (ico_man.svg)
                        if($scope.userProfile.imageName !== null){
                            $scope.selectedImg = {
                                imageID:    $scope.userProfile.imageID,
                                profileID:  $scope.userProfile.profileID,
                                name:  $scope.userProfile.imageName
                            };
                        }
                    }

                    // profile not found
                    else if(response.status == 404){
                        $scope.userProfile = {};
                    }

                    // on error
                    else {
                        console.log('could not load user profile');
                    }
                }
            );
        };

        $scope.refreshUserProfile = function () {

            // hide user images dropzone
            $scope.showDropzone = false;

            // display flash message
            $("#alert").show();
            $('#alert').html($scope.text[40]);

            // fade and remove flash message
            setTimeout(function(){
                $('#alert').fadeOut(2000,function(){$(this).remove();});
            },3000);

            // delay refresh for uploading images to complete
            setTimeout(function(){

                ajaxService.fn(
                    $scope.us.ajaxurl,    // URL
                    { 'mobileID': $scope.userProfile.mobileID },         // Parameters
                    function (response) {           // Callback function
                        if(response.status == 200){

                            $scope.userProfile = response.data;

                            // Update gallery selected image
                            $scope.selectedImg = false;

                            // display flash message
                            $("#alert").show();
                            $('#alert').html($scope.text[50]);

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').fadeOut(2000,function(){$(this).remove();});
                            },3000);
                        }

                        // profile not found
                        else if(response.status == 404){
                            console.log('user profile not found on server.');
                        }

                        // on error
                        else {
                            console.log('could not load user profile, server error.');
                        }
                    }
                );
            },2000);
        };

        // get profiles for homepage gallery, profiles pools are defined by browser language and domain (p.e. : flirddy.com in DE browser: pools are flirddy DE, flirddy AT and flirddy CH
        ajaxService.fn(
            $scope.us.ajaxurl,    // URL
            {'profiles':1},                // Parameters
            function (response) {           // Callback function
                if(response.status == 200){
                    $scope.profiles = response.data;

                    // DEBUG: enter here a profiles length
                    $scope.test = 23;
                    t = $scope.test;
                    $scope.test = t - (t - 15) % 4;

                    //if($scope.test % 4 != 0) $scope.test = $scope.test - (($scope.test - 1) % 4) -1; // force test val to modulo 4



                    // force profiles length to modulo 4 for grid layout
                    l = $scope.profiles.length;

                    $scope.profiles.length = l - (l - 15) % 4;
                    //if($scope.profiles.length % 4 != 0) $scope.profiles.length = $scope.profiles.length - (($scope.profiles.length - 1) % 4) -1;

                } else {
                    console.log('could not load profiles. status code: ' + response.status);
                    $scope.profiles = {};
                }
            }
        );


        $scope.getCountries = function(){

            // get countries
            ajaxService.fn(
                $scope.us.ajaxurl,    // URL
                {'countries':1},                // Parameters
                function (response) {           // Callback function
                    if(response.status == 200){
                        $scope.countries = response.data;

                        // set default
                        $scope.countrySelection = $scope.countries;
                        $scope.countrySelection.selected = $scope.countrySelection[0];
                        //$('#msisdn').html($scope.countrySelection.selected.prefix_int);

                    } else {
                        console.log('could not load countries. status code: ' + response.status);
                        $scope.countries = {};
                    }
                }
            );
        }();

        // Check if a user session->auth allready exists on server on refreshing browser
        if($scope.us.auth){

            // get user profile
            $scope.getUser($scope.us.mobileID);

            // Get user Chats
            $scope.getChats();
        }

        $scope.validateMsisdn = function(e){

            // open Tan submit div
            $scope.open = true;

            // empty error messages
            $('#msisdnError').html('');
            $('#tanError').html('');
            $('#msisdnError').html('');
            $('#tan').html('');

            // validate submitted MSISDN (one non-capturing optional heading zero)
            var msisdn = $("#msisdn").val().match(/^(?:0|)([1-9]{1}[0-9]{5,12})$/);

            // invalid MSISDN
            if(!msisdn){

                // close TAN-login div
                //$scope.open = !$scope.open;

                $('#msisdnError').html($scope.text[160]).css('color','red').css('font-weight','bolder');
            }

            // submit MSISDN
            else{

                ajaxService.fn(
                $scope.us.ajaxurl,
                { 'submitMsisdn': $scope.countrySelection.selected.prefix_int + msisdn[1] },
                function (response) {

                    // response
                    if(response.status == 200){

                        // on success
                        if(response.data.status == 201){
                            $('#msisdnError').html($scope.text[240]).css('color','#4cae4c').css('font-weight','bolder');

                            // save TAN and user mobile in session
                            $scope.us.tan = response.data.data.tan;
                            $scope.us.mobileID = response.data.data.mobileID;
                            $scope.us.msisdn = response.data.data.msisdn;

                            // Give focus to TAN input
                            $scope.giveFocus('tan');
                        }

                        // on error
                        else if(response.data.status == 500){
                            $('#msisdnError').html(response.data.data).css('color','red').css('font-weight','bolder');
                        }

                        // MSISDN not found
                        else if(response.data.status == 404){
                            $('#msisdnError').html('Leider bist Du noch kein Nutzer. Dieser Bereich ist nur für Mitglieder.').css('color','red').css('font-weight','bolder');
                        }

                        // user Messages not found
                        else if(response.data.status == 406){
                            $('#msisdnError').html('Existiert bisher kein Chat zur eingegebenen MSISDN.').css('color','red').css('font-weight','bolder');
                        }

                        // user allready requested a TAN in the last 20 minutes
                        else if(response.data.status == 429){

                            // save TAN and user mobile in user session
                            $scope.us.tan = response.data.data.tan;
                            $scope.us.mobileID = response.data.data.mobileID;
                            $scope.us.msisdn = response.data.data.msisdn;

                            $('#msisdnError').html('Sie haben bereits eine TAN angefordert. Geben Sie Ihren TAN ein, oder warten Sie bis zu 20 Minuten, um eine neue anfordern zu können.').css('color','red').css('font-weight','bolder');
                        }
                    }

                    //
                    else if(response.status == 204){

                    }

                    // on error
                    else {
                        console.log('could not submit MSISDN');
                    }
                });
            }
        };

        $scope.validateTan = function(e){

            // empty error messages
            $('#tanError').html('');
            $('#msisdnError').html('');

            // validate TAN
            if(! /^.{4,12}$/.test($('#tan').val())){

                // close TAN-login div
                //$scope.open = !$scope.open;

                $('#tanError').html('Ungültige Tan').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
            }

            // submit TAN
            else{

                ajaxService.fn(
                $scope.us.ajaxurl,            // URL
                { 'submitTan': $('#tan').val() },       // Parameters
                function (response) {                   // Callback function

                    //
                    if(response.status == 200){

                        // msisdn not submitted
                        if(response.data.status == 401){
                            $('#tanError').html('Bitte geben sie zuerst eine Mobilnummer ein.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // Tan list is empty for this mobile
                        else if(response.data.status == 402){
                            $('#tanError').html('Sie haben noch keine TAN angefordert.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // submitted TAN found but out of time ( > 20 min)
                        else if(response.data.status == 403){
                            $('#tanError').html('Die angegebene TAN ist nicht mehr gültig.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // submitted TAN not found in the TAN list
                        else if(response.data.status == 405){
                            $('#tanError').html('Die angegebene TAN ist falsch.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // on success
                        else if(response.data.status == 201){

                            $('#tanError').html('erfolgreiche Identifizierung.').css('color','#4cae4c').css('float','left').css('font-weight','bolder').css('margin-top','25px');

                            // remove login modal
                            $('#elegantModalForm').modal('hide');
                            $('body').removeClass('modal-open');
                            $('.modal-backdrop').remove();

                            // save TAN and user mobile in session
                            $scope.us.tan = response.data.data.tan;
                            $scope.us.mobileID = response.data.data.mobileID;
                            $scope.us.msisdn = response.data.data.msisdn;

                            // authentication
                            $scope.us.auth = true;

                            // get user profile
                            $scope.getUser($scope.us.mobileID);

                            // Get user's Chats
                            $scope.getChats();

                            // get url from thumb from first random profile to display in head of bootbox
                            url = $scope.us.resourceUrl+'/p'+$scope.profiles[0].profileID+'/'+ $scope.profiles[0].thumbName;

                            // display welcome boobtbox
                            bootbox.dialog({
                                message: $scope.text[250],
                                size: 'medium',
                                className: 'center',
                                backdrop: true,             // close when outside click or escape
                                onEscape: true,              // close when outside click or escape (both options are needed for .dialog, but not for alert, confirm or prompt)
                                buttons: [
                                      {
                                        label: "Mein Profil",
                                        className: "inline font-16 btn btn-"+$scope.us.scheme+"-sec grow",
                                        callback: function() {

                                            // switch to user view
                                            $scope.switchViews.showUser = true;
                                            $scope.switchViews.showProfiles = false;

                                            // updating the DOM
                                            $scope.$apply();
                                        }
                                      },
                                      {
                                        label: "Sofort chatten",
                                        className: "inline font-16 grow btn btn-"+$scope.us.scheme,
                                        callback: function() {

                                            // switch to user view
                                            $scope.switchViews.showUser = false;
                                            $scope.switchViews.showProfiles = true;

                                            // updating the DOM
                                            $scope.$apply();
                                        }
                                      }
                                    ],
                            })
                            .find('.modal-content').addClass("welcome") // customize content
                            .find('.modal-footer').addClass("welcome-footer"); // customize child footer

                            // auto close bootbox
                            window.setTimeout(function(){
                                //bootbox.hideAll();
                            }, 5000); // 10 seconds expressed in milliseconds

                        }

                        // on error
                        else if(response.data.status == 500){
                            $('#tanError').html(response.data.data).css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // tan not found
                        else if(response.data.status == 404){
                            $('#tanError').html('Leider bist Du noch kein Nutzer. Dieser Bereich ist nur für Mitglieder.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // Messages not found
                        else if(response.data.status == 406){
                            $('#tanError').html('Existiert bisher kein Chat zur eingegebenen tan.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }

                        // user allready asked for a TAN in the last 20 minutes
                        else if(response.data.status == 429){
                            $('#tanError').html('Sie haben bereits eine TAN angefordert. Bitte warten Sie bis zu 20 Minuten, um eine neue anfordern zu können.').css('color','red').css('float','left').css('font-weight','bolder').css('margin-top','25px');
                        }
                    }

                    //
                    else if(response.status == 204){

                    }

                    // on error
                    else {
                        console.log('could not load user profile');
                    }
                });

            }
        };

        /* User profile Form validation */
        $scope.validateForm = function(e){

            // Error messages spans
            $('#nameError').html('');
            $('#ageError').html('');
            $('#heightError').html('');
            $('#weightError').html('');
            $('#descriptionError').html('');

            // Invalid Name
            if ( !$("#name").val() || ! /^[a-zA-Z0-9_-]{2,20}$/.test($("#name").val()) ) {
                $('#nameError')
                    .html(' * Allowed length 3 to 20 characters.')
                    .css("color", "red");
            }

            // Name is valid, check if name is unique
            else {

                // Set PoolID
                var poolID = angular.isDefined($scope.userProfile.poolID)
                    ? $scope.userProfile.poolID
                    : 0;

                // prepare ajax data to check unique name-poolID
                var ajax_data = {
                    'profile_is_unique': {
                        'name': $("#name").val().toLowerCase(),
                        'poolID': poolID
                        }
                };

                // Check unique name-poolID, if OK then validate the other form inputs.
                ajaxService.fn(
                    $scope.us.ajaxurl,    // URL
                    ajax_data,                      // data
                    function (response) {           // Callback function

                        // response = 200: a profile allready exists with name-poolID
                        // check if the 2 profileIDs equal, if yes then update profile
                        if (response.status == 200 && (response.data != $scope.userProfile.profileID)) {
                            $('#nameError').html(' * allready exists').css("color", "cyan");
                            e.preventDefault();
                        }
                        else if (!$("#age").val() || $("#age").val() < 18) {
                            $('#ageError').html(' * 18+').css("color", "cyan");
                        }
                        else if (!$("#height").val() || $("#height").val() > 250) {
                            $('#heightError').html(' * is not a correct value').css("color", "cyan");
                        }
                        else if (!$("#weight").val() || $("#weight").val() > 250) {
                            $('#weightError').html(' * is not a correct value').css("color", "cyan");
                        }
                        else if ($("#description").val().length > 495) {
                            $('#descriptionError').html(' * Dein Profiltext is  '+($("#description").val().length + 5)+' zeichen lang (sollte nicht mehr als 500 zeichen heinhalten.)').css("color", "cyan");
                        }
                        // Submit Form
                        else {
                            $('#profileForm').submit();
                        }
                    }
                );

            }
            e.preventDefault(); // ajax is async, no submit form here, but well after ajax response and inputs validation (above lines)
        };

        $scope.checkCredits = function(e){

            // empty error messages
            $('#sentError').html('');

            // no credits
            if($scope.credits.count == 0){

                // open payment modal
                $('#paymentForm').modal('show');
            }
        };

        $scope.charge = function(e){

            $scope.credits.count = 100;

            $('#paymentForm').modal('hide');
        };

        $scope.sendMO = function(e){

            // empty error messages
            $('#sentError').html('');

            // validate TAN
            if(! /^.{1,150}$/.test($('#mo').val())){

                $('#sentError').html('Sende ' + $scope.profileName + ' eine nette Nachricht').css('color','#00b663').css('float','left').css('position','absolute').css('margin','-20px 0px 0px 10px');
            }

            // send MO
            else{

                // prepare ajax data
                var ajaxData = {
                    'proceed_message': {
                        'profileID': $scope.profileID,
                        'text': $('#mo').val()
                        }
                };

                ajaxService.fn(
                $scope.us.ajaxurl,            // URL
                ajaxData, // Parameters
                function (response) {                   // Callback function

                    console.log(response);

                    //
                    if(response.status == 200){

                        // on success
                        if(response.data.status == 201){

                            // reload chat
                            $scope.getChat($scope.profileID, $scope.profileName, $scope.profileImageName);

                            // payment
                            $scope.credits.count = $scope.credits.count - 1;

                        }

                        // on error
                        else if(response.data.status == 500){
                            $('#msisdnError').html(response.data.data).css('color','red').css('float','left').css('font-weight','bolder');
                        }

                    }

                    // on error
                    else {
                        console.log('could not send message');
                    }
                });

            }

            // empty input field
            $("#mo").val("");
        };

        // validate files upload
        $('.images').change(function(){

            var _URL = window.URL || window.webkitURL; // Gecko || Chrome

            // declare some variables
            var i = 0, files = this.files, file = files[files.length - 1];

            // Max upload file 2 MB
            if(file.size > 2000000){
                $('#pics').html('Max upload video size 2MB').css('color','red');
                $('#add_pic').hide();
                $('#save-changes').hide();
            }
            else {
                $('#pics').html('');
                $('#add_pic').show();
                $('#save-changes').show();
            }
        });

        // validate further added files ("add another picture" function)
        $("#img").bind("DOMSubtreeModified", function() {

            $('.images').change(function(){

                var _URL = window.URL || window.webkitURL; // Gecko || Chrome

                // declare some variables
                var i = 0, files = this.files, file = files[files.length - 1];

                // Max upload file 2 MB
                if(file.size > 2000000){
                    $('#pics').html('Max upload video size 2MB').css('color','red');
                    $('#add_pic').hide();
                    $('#save-changes').hide();
                }
                else {
                    $('#pics').html('');
                    $('#add_pic').show();
                    $('#save-changes').show();
                }
            });
        });

        /* Add upload file system button */
        $scope.btnAdded = {count: 0};
        $scope.addBtn = function(e){
            var $clone = $('#hidden_btn').clone().attr('id','').removeClass('hidden');
            $('#hidden_btn').before($clone);
            var $clone = $('#hidden_checkbox').clone().attr('id','').removeClass('hidden');
            $('#hidden_btn').before($clone);
            var $clone = $('#hidden_label').clone().attr('id','').removeClass('hidden');
            $('#hidden_btn').before($clone);

            $scope.btnAdded.count ++;
        };

        // "load more" profiles images config
        $scope.images = [15, 16, 17, 18];

        // Use either this one (with infinite scroll js) or the loadMore function.
        $scope.loadInfiniteProfiles = function() {

            var last = $scope.images[$scope.images.length - 1];

            for(var i = 1; i <= 4; i++) {

                // end of datas
                if($scope.images[$scope.images.length - 1] != $scope.profiles.length - 1){

                    //$scope.images.push(last + i);

                }
                else{
                    $scope.endOfData = true;
                }

            }
        };

        // Use either this one or the loadInfiniteProfiles function (with infinite scroll js).
        $scope.loadMore = function(){

            var last = $scope.images[$scope.images.length - 1];

            for(var i = 1; i <= 8; i++) {

                if($scope.images[$scope.images.length - 1] != $scope.profiles.length - 1){

                    $scope.images.push(last + i);
                }
                else{
                    $scope.endOfData = true;
                }
            }
        };

        // anchorScroll needs $location, and enable html5 in angularjs (see app.config(function($locationProvider)), because angularjs adds a # in the location
        $scope.scrollTo = function(scrollLocation){

            // Add #{scrollLocation} to current URL
            $location.hash(scrollLocation);

            // Scrolls to the hash element
            $anchorScroll();

            // Remove hash from URL (cosmetic)
            $location.hash(null);

            // DEV: this is not working
            //anchorSmoothScroll.scrollTo(scrollLocation);
        };

        // return switch profile thumbName (default) or profile imageName
        $scope.getUrl = function(index){

            return "{'background-image': thumb"+index+"==true  ? ' url(' + us.resourceUrl+'/p'+profiles["+index+"].profileID+'/'+ profiles["+index+"].imageName + ')' : ' url(' + us.resourceUrl+'/p'+profiles["+index+"].profileID+'/'+ profiles["+index+"].thumbName + ')','transition':'background-image 0.5s ease-out, background-size 0s ease-out'}";
        };

        // return profile thumbName (default) or profile imageName (with scope local boolean variable)
        $scope.getBackgroundUrl = function(index){

            return "{'background-image': thumb==true  ? ' url(' + us.resourceUrl+'/p'+profiles["+index+"].profileID+'/'+ profiles["+index+"].thumbName + ')' : ' url(' + us.resourceUrl+'/p'+profiles["+index+"].profileID+'/'+ profiles["+index+"].imageName + ')','transition':'background-image 0.5s ease-out, background-size 0s ease-out'}";
        };

        // Set background image
        $scope.bgImg = function(index, thumb){

            // profile image or profile thumbnail
            var name = thumb ? 'thumbName' : 'imageName';

            // image url or thumbnail url
            var url =  $scope.us.resourceUrl+'/p' + $scope.profiles[index].profileID +'/'+ $scope.profiles[index][name];

            // image size
            var img = new Image();
            img.addEventListener("load", function(){

                width = this.naturalWidth;
                height = this.naturalHeight;

                // get DOM element
                el = thumb ? "."+index+"t" : "."+index;
                element = $(el);

                // add background image
                element.css("background-image", "url("+url+")");

                // set background size to 100% auto for portrait (vs. landscape)
                if(width < height){
                    element.addClass('bg-size-100-auto');
                }
                else{
                    element.addClass('bg-size-auto-100');
                }
            });
            img.src = url;
        };

        // Call an image url and get its metadata
        $scope.getMeta = function(url){
            var img = new Image();
            var width, height;
            img.addEventListener("load", function(){

                width = this.naturalWidth;
                height = this.naturalHeight;

            });
            img.src = url;
        }

        // return profile to string
        $scope.profileToString = function(index){

            return "profiles["+index+"].profileID, profiles["+index+"].profileName, us.resourceUrl+'/p'+profiles["+index+"].profileID+'/'+profiles["+index+"].thumbName";
        };

        $scope.updateUserProfile = function(prop, el){

            // create new profile
            if($scope.userProfile === undefined){
                $scope.userProfile = {
                    profileName : ''
                }
            }

            // rename prop
            if(prop == 'name') prop = 'profileName';

            // get DOM element
            el = "#"+el;
            element = $(el);

            // update model (if different)
            if(element.val() != $scope.userProfile[prop]){

                // rename prop
                if(prop == 'profileName') prop = 'name';

                // prepare ajax data
                var ajaxData = {
                    'updateUserProfile': {
                        'profileID': $scope.userProfile.profileID,
                        'mobileID' : $scope.us.mobileID
                        }
                };

                ajaxData.updateUserProfile[prop] = element.val();

                ajaxService.fn(
                    $scope.us.ajaxurl,
                    ajaxData,
                    function (response) {
                        if(response.status == 200){

                            // update scope
                            $scope.getUser($scope.us.mobileID);

                            // display flash message
                            $("#alert").show();
                            $('#alert').html($scope.text[50]);

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').fadeOut(2000,function(){$(this).hide();});
                                },3000);

                        } else {
                            console.log('could not update user profile');
                        }
                    }
                );
            }
        };

        $scope.updateUserImg = function(type, profileID, imageID){

            // prepare ajax data
            var ajaxData = {};
            ajaxData[type] = {
                'profileID' : profileID,
                'imageID'   : imageID
            };

            ajaxService.fn(
                $scope.us.ajaxurl,
                ajaxData,
                function (response) {
                    if(response.status == 200){
                    } else {
                        console.log('could not update user image');
                    }
                }
            );
        };

        $scope.giveFocus = function(el){

            // First setting timeout because sometimes is the state of the element in ng-show process, setTimeout will wait for DOM show end process
            setTimeout(function() {
                $("#"+el).focus();
            }, 500);
        };

        $scope.userSessionUrl = function(url, path, params){

            var index = url.indexOf("?");
            var output = url.substr(0, index) + path.toLowerCase() + '/' + url.substr(index) + ( (params !== undefined) ? '&' + params : '');

            console.log(output);

            // same window
            window.location.href = output;

            // new window
            //window.open(output);
        };

        $scope.home = function(){
            window.location.href = $scope.us.sameurl;
        };

        // Highcharts Tan Users
        $scope.tanUsersStats = function(e){

            // Only load once
            if(angular.isUndefined($scope.tanUsersSeries)){

                // initialize datepicker variables
                if(angular.isUndefined($scope.dateTanUsers)){
                    $scope.dateTanUsers = {
                        from: new Date(),
                        to: new Date()
                    };
                    var from = new Date().setDate($scope.dateTanUsers.from.getDate()-30);
                    var to = new Date().setDate($scope.dateTanUsers.to.getDate()+1);
                    $scope.dateTanUsers.from = new Date(from);
                    $scope.dateTanUsers.to = new Date(to);
                    }

                // display alert message
                $("#alert").show();
                $('#alert').html('Wird geladen ...');

                // prepare ajax data
                var ajaxData = {
                    'tan_users': {
                        'from': $scope.dateTanUsers.from,
                        'to': $scope.dateTanUsers.to
                        }
                };

                ajaxService.fn(
                    $scope.us.ajaxurl,
                    ajaxData,
                    function (response) {
                        if(response.status == 200){

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').fadeOut(2000,function(){$(this).remove();});
                            },3000);

                            // Initialize Highchart data
                            $scope.tanUsersDates = new Array();
                            $scope.tanUsersSeries =  [
                                { name: "Flirddy", data: [], color: '#FF9933' },
                                { name: "CherryChat", data:[], color: '#EF00ED' },
                                { name: "Chat-Fever", data:[], color: '#90ed7d' },
                            ];

                            // cast object to array
                            values = Object.values(response.data);

                            for(var i= 0; i < values.length; i++) {

                                // build dates Highchart serie
                                $scope.tanUsersDates.push(values[i].name);

                                // initialize each scheme counter
                                flirddy = 0;
                                cherry = 0;
                                fever = 0;

                                for(var j= 0; j < values[i].users.length; j++) {

                                    // parse JSON to object
                                    val = JSON.parse(values[i].users[j].data);

                                    // increment each scheme counter
                                    if(val.scheme == 'flirddy') flirddy++;
                                    else if(val.scheme == 'cherry') cherry++;
                                    else if(val.scheme == 'fever') fever++;
                                }

                                // prepare for Highchart
                                $scope.tanUsersSeries[0].data.push(flirddy);
                                $scope.tanUsersSeries[1].data.push(cherry);
                                $scope.tanUsersSeries[2].data.push(fever);
                            }

                            var chart = $('#tan-charts').highcharts(
                                {
                                    title: {
                                        text: '',
                                        style: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        }
                                    },
                                    legend: {
                                        itemStyle: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        }
                                    },
                                    chart: {
                                        type: 'line',
                                        backgroundColor:'rgba(0,0,0,0)',
                                        color: "rgb(189, 193, 195)",
                                        marginLeft: 100,
                                        marginRight: 100
                                    },
                                    xAxis: {
                                        labels: {
                                            style: {
                                                color:"rgb(189, 193, 195)",
                                            }
                                        },
                                        title: {
                                            text: '',
                                            style: {
                                                color: '',
                                                font: ''
                                            }
                                        },
                                        categories: $scope.tanUsersDates,
                                    },
                                    yAxis: {

                                        lineWidth: 1,
                                        title: {
                                            text: ' ',
                                            style: {
                                                color: 'white',
                                                fontSize: '18px'
                                            }
                                        },
                                        labels: {
                                            style: {
                                                color: 'white'
                                            }
                                        },


                                    },
                                    tooltip: {
                                        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                                        pointFormat: '<tr><td style="padding:0">{series.name}&nbsp;&nbsp;</td>' +
                                            '<td style="padding:0"><b>{point.y}</b></td></tr>',
                                        footerFormat: '</table>',
                                        shared: true,
                                        useHTML: true,
                                        style: {
                                            color: 'black',
                                            fontWeight: 'bold'
                                        }
                                    },
                                    series: $scope.tanUsersSeries
                                }
                            ); // end .highcharts

                        } else {

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').fadeOut(2000,function(){$(this).remove();});
                            },3000);
                            console.log('could not load tan_users');
                        }
                    }
                );
            }
        };

        // Highcharts visits Users
        $scope.visitsUsersStats = function(e){

            // Only load once
            if(angular.isUndefined($scope.visitsUsersSeries)){

                // initialize datepicker variables
                if(angular.isUndefined($scope.dateVisitsUsers)){
                    $scope.dateVisitsUsers = {
                        from: new Date(),
                        to: new Date()
                    };
                    var from = new Date().setDate($scope.dateVisitsUsers.from.getDate()-30);
                    var to = new Date().setDate($scope.dateVisitsUsers.to.getDate()+1);
                    $scope.dateVisitsUsers.from = new Date(from);
                    $scope.dateVisitsUsers.to = new Date(to);
                    }

                // prepare ajax data
                var ajaxData = {
                    'visits': {
                        'from': $scope.dateVisitsUsers.from,
                        'to': $scope.dateVisitsUsers.to
                        }
                };

                ajaxService.fn(
                    $scope.us.ajaxurl,
                    ajaxData,
                    function (response) {
                        if(response.status == 200){

                            $scope.visitsUsersDates = new Array();
                            $scope.visitsUsersSeries =  [
                                { name: "Flirddy", data: [], color: '#FF9933' },
                                { name: "CherryChat", data:[], color: '#EF00ED' },
                                { name: "Chat-Fever", data:[], color: '#90ed7d' },
                            ];

                            // cast object to array
                            values = Object.values(response.data);

                            for(var i= 0; i < values.length; i++) {

                                // build dates serie
                                $scope.visitsUsersDates.push(values[i].name);

                                flirddy = 0;
                                cherry = 0;
                                fever = 0;

                                for(var j= 0; j < values[i].users.length; j++) {

                                    val = JSON.parse(values[i].users[j].data);
                                    if(val.scheme == 'flirddy') flirddy++;
                                    else if(val.scheme == 'cherry') cherry++;
                                    else if(val.scheme == 'fever') fever++;

                                }

                                // parse data json to object
                                //value = JSON.parse(response.data[i].data);

                                //if(value.scheme == 'flirddy')

                                $scope.visitsUsersSeries[0].data.push(flirddy);
                                $scope.visitsUsersSeries[1].data.push(cherry);
                                $scope.visitsUsersSeries[2].data.push(fever);
                            }

                            var chart = $('#visits-charts').highcharts(
                                {
                                    title: {
                                        text: '',
                                        style: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        }
                                    },
                                    legend: {
                                        itemStyle: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        }
                                    },
                                    chart: {
                                        type: 'line',
                                        backgroundColor:'rgba(0,0,0,0)',
                                        color: "rgb(189, 193, 195)",
                                        marginLeft: 100,
                                        marginRight: 100
                                    },
                                    xAxis: {
                                        labels: {
                                            style: {
                                                color:"rgb(189, 193, 195)",
                                            }
                                        },
                                        title: {
                                            text: '',
                                            style: {
                                                color: '',
                                                font: ''
                                            }
                                        },
                                        categories: $scope.visitsUsersDates,
                                    },
                                    yAxis: {

                                        lineWidth: 1,
                                        title: {
                                            text: ' ',
                                            style: {
                                                color: 'white',
                                                fontSize: '18px'
                                            }
                                        },
                                        labels: {
                                            style: {
                                                color: 'white'
                                            }
                                        },


                                    },
                                    tooltip: {
                                        headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                                        pointFormat: '<tr><td style="padding:0">{series.name}&nbsp;&nbsp;</td>' +
                                            '<td style="padding:0"><b>{point.y}</b></td></tr>',
                                        footerFormat: '</table>',
                                        shared: true,
                                        useHTML: true,
                                        style: {
                                            color: 'black',
                                            fontWeight: 'bold'
                                        }
                                    },
                                    series: $scope.visitsUsersSeries
                                }
                            ); // end .highcharts

                        } else {

                            // remove message "Loading profiles..." from navbar
                            $("#alert").hide();
                            console.log('could not load visits_users');
                        }
                    }
                );
            }
        };
	}]);


    /*
     *  jQuery events and function
     */
    $(document).ready(function() {

        // Enable Tooltipster
        $('.tooltipster-bottom').tooltipster({
            position: 'bottom',
            contentAsHTML: true,
            interactive: true,
            delay: 50
        });
        $('.tooltipster-right').tooltipster({
            position: 'right',
            contentAsHTML: true,
            interactive: true,
            delay: 50
        });
        $('.tooltipster-left').tooltipster({
            position: 'left',
            contentAsHTML: true,
            interactive: true,
            delay: 50
        });


        // Blur (loose focus) on this input element when Enter key is pressed
        function keydown (e){
            if(e.keyCode===13){ // 13 is the code of the Enter key
                document.getElementById('age-input').blur();
                var blurOnEnter = document.getElementsByClassName("blur-on-enter");
                for (var i = 0; i < blurOnEnter.length; i++) {
                    blurOnEnter[i].blur();
                }
            }
        }

        // Add keydown event listener to user profile inputs
        var blurOnEnter = document.getElementsByClassName("blur-on-enter");
        for (var i = 0; i < blurOnEnter.length; i++) {
            blurOnEnter[i].addEventListener('keydown', keydown);
        }

    });

	// Bootstrap button tooltip initialize
	$('[data-toggle="tooltip"]').tooltip();
    $('[data-toggle="popover"]').popover();

	//$('[data-toggle="tooltip"]').on('click', function () { $(this).tooltip('hide')});
	//$('[data-toggle="tooltip"]').on('mouseout', function () {$(this).tooltip('hide')});
})(jQuery);

/* **********************************************  perfect-scrollbar angular module (need perfect-scrollbar js plugin) *******************************/
angular.module('perfect_scrollbar', []).directive('perfectScrollbar',
  ['$parse', '$window', function($parse, $window) {
  var psOptions = [
    'wheelSpeed', 'wheelPropagation', 'minScrollbarLength', 'maxScrollbarLength', 'useBothWheelAxes',
    'useKeyboard', 'suppressScrollX', 'suppressScrollY', 'scrollXMarginOffset',
    'scrollYMarginOffset', 'includePadding'//, 'onScroll', 'scrollDown'
  ];

  return {
    restrict: 'EA',
    transclude: true,
    template: '<div><div ng-transclude></div></div>',
    replace: true,
    link: function($scope, $elem, $attr) {
      var jqWindow = angular.element($window);
      var options = {};

      for (var i=0, l=psOptions.length; i<l; i++) {
        var opt = psOptions[i];
        if ($attr[opt] !== undefined) {
          options[opt] = $parse($attr[opt])();
        }
      }

      $scope.$evalAsync(function() {
        $elem.perfectScrollbar(options);
        var onScrollHandler = $parse($attr.onScroll)
        $elem.scroll(function(){
          var scrollTop = $elem.scrollTop()
          var scrollHeight = $elem.prop('scrollHeight') - $elem.height()
          var scrollLeft = $elem.scrollLeft()
          var scrollWidth = $elem.prop('scrollWidth') - $elem.width()

          $scope.$apply(function() {
            onScrollHandler($scope, {
              scrollTop: scrollTop,
              scrollHeight: scrollHeight,
              scrollLeft: scrollLeft,
              scrollWidth: scrollWidth
            })
          })
        });
      });

      $scope.$watch(function() {
        return $elem.prop('scrollHeight');
      }, function(newValue, oldValue) {
        if (newValue) {
          update('contentSizeChange');
        }
      });

      function update(event) {
        $scope.$evalAsync(function() {
          if ($attr.scrollDown == 'true' && event != 'mouseenter') {
            setTimeout(function () {
              $($elem).scrollTop($($elem).prop("scrollHeight"));
            }, 100);
          }
          $elem.perfectScrollbar('update');
        });
      }

      // This is necessary when you don't watch anything with the scrollbar
      $elem.bind('mouseenter', update('mouseenter'));

      // Possible future improvement - check the type here and use the appropriate watch for non-arrays
      if ($attr.refreshOnChange) {
        $scope.$watchCollection($attr.refreshOnChange, function() {
          update();
        });
      }

      // this is from a pull request - I am not totally sure what the original issue is but seems harmless
      if ($attr.refreshOnResize) {
        jqWindow.on('resize', update);
      }

      $elem.bind('$destroy', function() {
        jqWindow.off('resize', update);
        $elem.perfectScrollbar('destroy');
      });

    }
  };
}]);


