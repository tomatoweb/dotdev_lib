
// reinitialize global variables (e.g. $), avoiding conflict with other .js
jQuery.noConflict();

// document ready wrapper
(function($){

    var app = angular.module('app', ['wu.masonry', 'ngSanitize', 'ui.select', 'ngMaterial','ngMessages', 'angularUtils.directives.dirPagination', 'perfect_scrollbar']);

    app.directive('maDatepicker', function() {
        // jquery-ui datepicker()
        // http://www.abequar.net/posts/jquery-ui-datepicker-with-angularjs (pass the selected date to ng-model)
        // https://docs.angularjs.org/guide/directive
        return {
            restrict: 'A',
            require : 'ngModel',
            link : function (scope, element, attrs, ngModelCtrl) {
                $(function(){
                    element.datepicker( {  // datepicker() is defined in jquery-ui.js
                        dateFormat:'yy-mm-dd',
                        onSelect:function (date) { // https://api.jqueryui.com/datepicker/#option-onSelect

                            // <input ng-model="dateFrom">
                            var prop = attrs.ngModel;

                            // assign input value (2017-09-11) to controller scope variable
                            scope.lifetimeDates[prop] = element.val();

                            // inject datepicker value in input field, and consequently to ng-model
                            ngModelCtrl.$setViewValue(date);

                            scope.$apply();
                        }
                    });
                });
            }
        }
    });

    app.directive('maMosCharts', ['$http', function($http) {
        return {
            restrict: 'EA',
            link : function (scope, element, attrs, ngModelCtrl, http) {
                $(function(){

                    $http({
                        method: 'POST',
                        url: scope.$root.user.session.ajaxurl,
                        data: { MOs_stats: 1  } // JS object is automaticaly converted in JSON by AngularJS http service
                    }).then(
                        function successCallback(response) {

                            // .then() method is used as a promise, because response may not be available immediately.
                            // successCallback() is the callback function that is called if the http request successfully respond.
                            // response can be usefully debugged with $log.info(response), don't forget to inject $log in app.controller('appCtrl', ['$scope', '$http','log', function($scope, $http, $log)
                            scope.MOstats = response.data; // JSON is automatically converted in JS object by AngularJS http service
                            scope.categories = new Array();
                            scope.series =  [
                                { name:"MOs", data:[], color: '#EF00ED' },
                            ];

                            for(var i= 0; i < scope.MOstats.length; i++) {
                                scope.categories.push(scope.MOstats[i].d);
                                scope.series[0].data.push(scope.MOstats[i].MOs);
                            }

                            $('#MOs').highcharts({
                                title: {
                                    text: 'MOs last 30 days',
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
                                    type: 'column',
                                    backgroundColor:'#3e3e3e',
                                    color: "rgb(189, 193, 195)"
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
                                    categories: scope.categories,
                                },
                                yAxis: {
                                    min: 0,
                                    title: {
                                        text: '',
                                        style: {
                                        color: 'rgb(189, 193, 195)',
                                        font: ''
                                        }
                                    },
                                    labels: {
                                        style: {
                                            color:"rgb(189, 193, 195)",
                                        }
                                    },
                                },
                                tooltip: {
                                    headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}&nbsp;&nbsp;</td>' +
                                        '<td style="padding:0"><b>{point.y}</b></td></tr>',
                                    footerFormat: '</table>',
                                    shared: true,
                                    useHTML: true
                                },
                                plotOptions: {
                                    column: {
                                        pointPadding: 0.2,
                                        borderWidth: 0
                                    }
                                },
                                series: scope.series
                            });

                        },
                        function errorCallback(response) {
                            scope.profilesHighchartsLoader.loading = false;
                            // called asynchronously if an error occurs or server returns response with an error status.
                            scope.error = response.data;
                            // $log.info or console.log
                        }
                    ); // end $http().then()
                }); // end $(function)
            } // end link
        } // end return
    }]);

    // ng-switch destroy its div, accordingly to this fact, no js will be initialy executed (div is not present in DOM till onclick).
    // Thus this directive will execute code after div creation by ng-click

/*

    app.directive('maProfilesCharts', ['$http', function($http) {

        return {
            restrict: 'EA',
            link : function (scope, element, attrs, ngModelCtrl, http) {

                $(function(){

                    scope.profilesHighchartsLoader = {loading:true};

                    $http({
                        method: 'POST',
                        url: scope.$root.user.session.ajaxurl,
                        data: { profiles_stats: 1  } // JS object is automaticaly converted in JSON by AngularJS http service
                    }).then(function successCallback(response) {
                        scope.profilesHighchartsLoader.loading = false;
                        // .then() method is used as a promise, because response may not be available immediately.
                        // successCallback() is the callback function that is called if the http request successfully respond.
                        // response can be usefully debugged with $log.info(response), don't forget to inject $log in app.controller('appCtrl', ['$scope', '$http','log', function($scope, $http, $log)
                        scope.jstats = response.data; // JSON is automatically converted in JS object by AngularJS http service

                        console.log('from directive.');
                        console.log(scope.jstats);

                        scope.categories = new Array();
                        scope.series =  [
                            { name: "Erhaltene MOs", data: [] },
                            { name: "Senders", data:[], color: '#EF00ED' },
                            { name: "Mittel MOs pro chat", data:[], color: '#90ed7d' },
                            { name: "Mittel Mos pro Tag l(etzten 30 T.)", data:[], color: '#ff9811' }
                        ];

                        for(var i= 0; i < scope.jstats.length; i++) {
                            scope.categories.push(scope.jstats[i].profileName);
                            scope.series[0].data.push(scope.jstats[i].messages_received);
                            scope.series[1].data.push(scope.jstats[i].senders);
                            scope.series[2].data.push(Math.round(scope.jstats[i].messages_received/scope.jstats[i].senders));
                            scope.series[3].data.push(scope.jstats[i].avg);
                        }

                        scope.chart = Highcharts.chart('container1', {

                            title: {
                                text: 'Statistics by profile',
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
                                type: 'column',
                                backgroundColor:'#3e3e3e',
                                color: "rgb(189, 193, 195)"
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
                                categories: scope.categories,
                            },
                            yAxis: {
                                min: 0,
                                title: {
                                    text: '',
                                    style: {
                                        color: 'rgb(189, 193, 195)',
                                        font: ''
                                    }
                                },
                                labels: {
                                    style: {
                                        color:"rgb(189, 193, 195)",
                                    }
                                },
                            },
                            tooltip: {
                                headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}&nbsp;&nbsp;</td>' +
                                    '<td style="padding:0"><b>{point.y}</b></td></tr>',
                                footerFormat: '</table>',
                                shared: true,
                                useHTML: true
                            },
                            plotOptions: {
                                column: {
                                    pointPadding: 0.2,
                                    borderWidth: 0
                                },
                                series: {
                                    events: {
                                        legendItemClick: function() {

                                            // The very first click on a serie's legend will initialize: var hidden = TRUE
                                            // The next clicks will switch the var 'hidden'
                                            this.hidden = !this.hidden;

                                            i = 0;

                                            this.yAxis.series.forEach(function(serie, index){

                                                if (!serie.hidden) {

                                                    if (i == 0) {

                                                        // chart.series[0] returns a object with a 'data' property (an array of points objects)
                                                        // chart.series[0].data[0] returns a point object, which you can then use it's 'update' method to change it's values.

                                                        //scope.chart.series[index].update(scope.series[index].data.sort(function(a, b){return a - b}).reverse());
                                                        // trier sur la série complète plutôt que la série courante.
                                                        scope.chart.series[index].update(scope.series[index].data.sort(function(a, b){return a - b}).reverse());

                                                        scope.chart.redraw();
                                                    }

                                                    i++;
                                                }
                                            });

                                        }
                                    }
                                }
                            },
                            series: scope.series
                        });
                    },
                    function errorCallback(response) {
                        scope.profilesHighchartsLoader.loading = false;
                        // called asynchronously if an error occurs or
                        //server returns response with an error status.
                        scope.error = response.data;
                    }); // end $http().then()
                }); // end $(function())
            } // end link
        } // end return
    }]);

*/

    app.directive('maClusterCharts', ['$http','ajaxService', function($http, ajaxService) {

        /* ajaxService will get data in getClustering() and put it in app.controller scope, this directive is only used by ng-switch */
        return {
            restrict: 'EA',
            link : function (scope, element, attrs, ngModelCtrl, http) {
                $(function(){
                    var chart;
                    chart = $('clustering-charts').highcharts(
                        {
                            title: {
                                text: '',
                                style: {
                                    color: 'rgb(189, 193, 195)',
                                    font: ''
                                }
                            },
                            legend: {
                                title: {
                                    style: {'color':'lightgrey'},
                                    text: 'MOs'
                                },
                                itemStyle: {
                                    color: 'rgb(189, 193, 195)',
                                    font: ''
                                },
                                x: -35,
                                y: -200
                            },
                            chart: { //http://www.highcharts.com/docs/chart-design-and-style/design-and-style
                                type: 'pie',
                                backgroundColor:'#3e3e3e',
                                color: "rgb(189, 193, 195)",
                                spacingTop: 50,
                                spacingLeft: 0,
                                spacingRight: 0,
                                spacingBottom: -200,
                                height: 218
                            },
                            tooltip: {
                                headerFormat: '<span style="font-size:11px">Range:&nbsp;{point.key} MOs</span><table>',
                                pointFormat: '<tr><td style="color:{series.color};padding:0">{point.percentage:.1f}%&nbsp;&nbsp;</td>' +
                                    '<td style="padding:0"><b>{point.y}&nbsp;</b></td></tr>',
                                footerFormat: '</table>',
                                shared: true,
                                useHTML: true
                            },
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    cursor: 'pointer',
                                    dataLabels: {
                                        distance:0,
                                        enabled: true,
                                        format: '<b>{point.name} MOs</b>: {point.percentage:.1f} %',
                                        style: {
                                            color:"rgb(189, 193, 195)",
                                            fontSize: "12px",
                                            fontWeight: "normal",
                                            textShadow: ""
                                        }
                                    },
                                    showInLegend: true
                                }
                            },
                            series: [
                                {
                                    type: 'pie',
                                    title: 'Users clustering',
                                    name: 'Users clustering',
                                    center: [200, null],
                                    size: 130,
                                    dataLabels: {
                                        enabled: false
                                    },
                                    showInLegend: true,
                                    data: scope.clusteringSeries
                                },
                                {
                                    type: 'pie',
                                    name: 'name1: MOs clustering',
                                    center: [500, null],
                                    size: 130,
                                    dataLabels: {
                                        enabled: false
                                    },
                                    showInLegend: true,
                                    data: scope.clusteringSeriesbis
                                }
                            ]
                        },
                        function(chart){
                            var datalabel;
                            //chart.series[0].data[1].dataLabel.translate(datalabel.x,data.plotY-20);
                            $.each(chart.series[0].data,function(j,data){
                                //if(data.y < 0)
                                {
                                    datalabel = data.dataLabel;
                                    //datalabel.translate(datalabel.x,data.y+1);
                                }
                            });
                        }
                    ); // end .highcharts
                }); // end $(function())
            } // end link
        } // end return
    }]);

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

    // confirm click
    app.directive('ngConfirmClick', [
        function(){
            return {
                link: function (scope, element, attr) {
                    var msg = attr.ngConfirmClick || "Are you sure?";
                    var clickAction = attr.confirmedClick;
                    element.bind('click',function (event) {
                        if ( window.confirm(msg) ) {
                            scope.$eval(clickAction)
                        }
                    });
                }
            };
    }]);

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

    app.filter('extractFilter', function() {
        return function(items, props) {
            var out = [];
            if (angular.isArray(items)) {
                items.forEach(function(item) {
                    var itemMatches = false;
                    var keys = Object.keys(props);
                    for (var i = 0; i < keys.length; i++) {
                        var prop = keys[i];
                         var text = props[prop].toString().toLowerCase();
                        if (item[prop].toString().toLowerCase() === text) {
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

    app.filter('picsFilter', function() {

        return function(items, props) {

            var out = '';
            var profilePics = 0;
            var chatPics = 0;
            var videos = 0;

            if (angular.isArray(items)) {
                items.forEach(function(item, index) {
                    // remove WVGA copy
                    if(item.name.toString().toLowerCase().indexOf('_') !== -1){
                         items.splice(index, 1);
                    }
                    // increment videos counter
                    if(item.name.charAt(0).toLowerCase() == 'v') videos++;
                    // increment chat or profile pics counter
                    else {
                        if(item.moderator == 0) profilePics++;
                        else chatPics++;
                    }
                });

                if(profilePics > 0) out += profilePics + ' profile pics. ';
                if(chatPics > 0) out += chatPics + ' chat pics. ';
                if(videos > 0) out += videos + ' videos.';

            } else {
                // Let the output be the input untouched
                out = items;
            }
            return out;
        }
    });

    // Format date 'dd.MM.yyyy - HH:mm:ss'
    app.filter('datetime', function ($filter) {

        return function(input){

            if(input == null){
                return "";
            }

            var _date = $filter('date')(new Date(input), 'dd.MM.yyyy - HH:mm:ss');

            return _date.toUpperCase();

         };
    });

    // Format date 'dd.MM.yyyy'
    app.filter('dateDay', function ($filter) {

        return function(input){

            if(input == null){
                return "";
            }
            input = input.replace(/(.+) (.+)/, "$1T$2Z"); // for Firefox and Safari
            var _date = $filter('date')(new Date(input), 'dd.MM.yyyy');

            return _date.toUpperCase();

         };
    });

    // Format date 'yyyy-mm-dd HH:mm:ss'
    app.filter('dateEngDay', function ($filter) {

        return function(input){

            if(input == null){
                return "";
            }

            var _date = $filter('date')(new Date(input), 'yyyy-MM-dd HH:mm:ss');

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

    // configure date format for md-datepicker
    app.config(function($mdDateLocaleProvider) {
        $mdDateLocaleProvider.formatDate = function(date) {
            if(!angular.isUndefined(date)){
                var day = date.getDate();
                var monthIndex = date.getMonth();
                var year = date.getFullYear();

                return day + '.' + (monthIndex + 1) + '.' + year;
            }
        };
    });

    // Trust external resources and cross domain resources
    app.filter('trusted', ['$sce', function ($sce) {
        return function(url) {
            return $sce.trustAsResourceUrl(url);
        };
    }]);

    app.service('ajaxService', [ '$http', function($http){
        return {
            fn: function(url, data, callback){
                $http({
                    method: 'POST',
                    url: url,
                    data: data
                }).then(
                    function successCallback(response) {
                        callback(response);
                    },
                    // called asynchronously if an error occurs or server returns response with an error status.
                    function errorCallback(response) {
                        callback(response);
                    }
                );
            }
        }
    }]);

    app.controller('appCtrl', ['$scope', '$filter', '$http', '$window', 'ajaxService', '$anchorScroll', function($scope, $filter, $http, $window, ajaxService, $anchorScroll) {

        // set scope variables
        $scope.since = JSON.parse('[{"period":"einen Monat", "months":1},{"period":"zwei Monaten", "months":2},{"period":"drei Monaten", "months":3},{"period":"vier Monaten", "months":4},{"period":"Alle", "months":120}]');
        $scope.since.selected = $scope.since[$scope.since.length -1];
        $scope.poolSelection= [];
        $scope.poolSelection["selected"] = {"poolID": "1"};
        $scope.searchProfile = "";
        $scope.searchUser = "";
        $scope.top50activity = [
            {"period":"Aktiv 48st."},
            {"period":"All users"}
        ];
        $scope.top50activity.selected = $scope.top50activity[0];
        $scope.top50activity = [
            {"period":"Aktiv 48st."},
            {"period":"All users"}
        ];
        $scope.top50activity.selected = $scope.top50activity[0];
        $scope.dateTop50 = {
            from: new Date(),
            to: new Date()
        };
        var startDate = new Date().setDate($scope.dateTop50.from.getDate()-30);
        $scope.dateTop50.from = new Date(startDate);
        $scope.dateClustering = {
            from: new Date(),
            to: new Date()
        };
        var startDateClustering = new Date().setDate($scope.dateClustering.from.getDate()-30);
        $scope.dateClustering.from = new Date(startDateClustering);
        $scope.dateUsersStats = {
            from: new Date(),
            to: new Date()
        };
        var startDateUsersStats = new Date().setDate($scope.dateUsersStats.from.getDate()-30);
        $scope.dateUsersStats.from = new Date(startDateUsersStats);
        $scope.activity = [
            {"name":"aktiv"},
            {"name":"inaktiv"}
        ];
        $scope.activity.selected = $scope.activity[0];
        $scope.periods = [
            {"amount":"24", "unit":"Stunden"},
            {"amount":"48", "unit":"Stunden"}
        ];
        $scope.periods.selected = $scope.periods[1];
        $scope.amount_MOs = [
            {"amount":"1"},
            {"amount":"2 - 10"}
        ];
        $scope.amount_MOs.selected = $scope.amount_MOs[1];
        $scope.clustering = [5,20,50,100,200];
        $scope.lifetime = JSON.parse('[{"users":"All users"}]');
        $scope.life = {};
        $scope.life.selected = {users: 'All users'};
        $scope.lifetimeDates = {dateFrom:0, dateTo:0};
        $scope.fskSelection = [
            {"age":"0","name":"0"},
            {"age":"16","name":"16"},
            {"age":"18","name":"18"},
            {"age":"","name":"Alle"}
        ];
        $scope.fskSelection.selected = $scope.fskSelection[$scope.fskSelection.length-1];

        // variables for use of module angularUtils.directives.dirPagination
        $scope.currentPage = 1;
        $scope.pageSize = 10;

        // angular views variables
        $scope.switchViews = {
            showProfiles        : true, // default view
            showUsers           : false,
            showHiddenProfiles  : false,
            showHiddenPictures  : false,
            showStats           : false,
            showChats           : false,
            showEvents          : false,
            showPools           : false,
            showChatPics        : false,
            showThumbs          : false,
            showProfilePics     : true, // default profile sub-view
            showChatVideos      : false,
            showResult          : false,
            showForm            : false
        };
        $scope.switchStatsViews = {
            showWorld       : true
        };

        // Show/hide booleans
        $scope.toggle               = { toggling: false };
        $scope.numbers              = { showing: false };
        $scope.chatProfilesShowing  = false;
        $scope.ShowCustomer         = false;
        $scope.profileSelect        = { showing: true };
        $scope.selectedProfile      = { showing: true, profileName: '' };
        $scope.selectedMobile       = { showing: true, msisdn: '' };
        $scope.mobile               = { showing: true };
        $scope.checkbox             = { showing: true };
        $scope.messages             = { data: {}, length: ''};
        $scope.customer             = {};

        // for demo version: disabled 'touchy' options
        if($scope.user.session.demo){

            $(".demo").each(function(index){
                $(this).attr('title', 'Nicht verfügbar im Demo-Modus');
                //$(this).addClass("disabled");
            });
        }

        // get countries
        ajaxService.fn(
            $scope.user.session.ajaxurl,    // URL
            {'countries':1},                // Parameters
            function (response) {           // Callback function
                if(response.status == 200){
                    $scope.countries = response.data;
                } else {
                    console.log(response);
                    console.log('could not load countries. status code: ' + response.status);
                    $scope.countries = {};
                }
            }
        );

        // get pools
        ajaxService.fn(
            $scope.user.session.ajaxurl,    // URL
            {'pools':1},                    // Parameters
            function (response) {           // Callback function

                if(response.status == 200) {

                    $scope.pools = response.data;

                    // set default pool select value for profiles
                    $scope.poolSelection = $scope.pools;
                    $scope.poolSelection.selected = $scope.poolSelection[0];

                    // set default pool select value for hidden profiles
                    $scope.poolSelectionHidden = angular.copy($scope.poolSelection);
                    $scope.poolSelectionHidden.selected = $scope.poolSelectionHidden[0];

                    // set default pool select value for images
                    $scope.poolPicSelection = angular.copy($scope.poolSelection);
                    $scope.poolPicSelection.selected = $scope.poolPicSelection[0];

                } else {
                    console.log('could not load pools');
                    $scope.pools = {};
                }
            }
        );

        // get profiles
        $scope.getAllProfiles = function(){

            ajaxService.fn(
                $scope.user.session.ajaxurl,    // URL
                { 'allProfiles': 1}, // Parameters
                function (response) {           // Callback function

                    if(response.status == 200){

                        $scope.allProfiles = response.data;
                    }

                    else{

                        console.log('could not load profiles');

                        $scope.allProfiles = {};
                    }
                }
            );
        }

        $scope.getProfiles = function(hidden){

            // authentication check
            if($scope.user.session.auth){

                // display message "Loading profiles..." in navbar
                $('#alert').html('Wird geladen ...').css("color","#47a447");

                // load profiles
                ajaxService.fn(
                    $scope.user.session.ajaxurl,    // URL
                    { 'profiles': 1, 'hidden': hidden, 'since': $scope.since.selected.months, 'poolID': $scope.poolSelection.selected.poolID }, // Parameters
                    function (response) {           // Callback function

                        if(response.status == 200){

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');

                            if(hidden == 0){
                                $scope.profiles = response.data;
                                $scope.backUpProfiles = angular.copy($scope.profiles);

                                $scope.getAllProfiles();

                                // filter profiles
                                //$scope.filterProfiles();
                            }
                            else{
                                $scope.hiddenProfiles = response.data;

                                $scope.backUpHiddenProfiles = angular.copy($scope.hiddenProfiles);

                                // filter hidden profiles
                                //$scope.filterHiddenProfiles();
                            }
                        }

                        else{

                            console.log('could not load profiles');

                            $scope.profiles = {};
                            $scope.hiddenProfiles = {};
                        }
                    }
                );
            }
        }

        $scope.sortProfiles = function(keyname){
            $scope.sortKey = keyname;   //set the sortKey to the param passed
            $scope.reverse = !$scope.reverse; //if true make it false and vice versa
        }

        // get active profiles
        $scope.getProfiles('0');

        $scope.getUsers = function(e){

            // Load customers profiles if not defined
            if(angular.isUndefined($scope.users)){

                // display message "Loading profiles..." in navbar
                $('#alert').html('Wird geladen ...').css("color","#47a447");

                ajaxService.fn(
                    $scope.user.session.ajaxurl,    // URL
                    { 'users': 1 },              // Parameters
                    function (response) {           // Callback function
                        if(response.status == 200){

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');

                            // assign
                            $scope.users = response.data;

                        } else {
                            console.log('could not load users');
                        }
                    }
                );
            }
        }

        $scope.getProfile = function (profileID) {

            ajaxService.fn(
                $scope.user.session.ajaxurl,    // URL
                { 'profileID': profileID },     // Parameters
                function (response) {           // Callback function
                    if(response.status == 200){
                        $scope.profile = response.data;
                        if($scope.profile.poolID == 0) $scope.profile.poolID = 1;
                    } else {
                        console.log('could not load profile');
                    }
                }
            );
        };

        /* Profile Form validation */
        $scope.validateForm = function(e){

            // clear error messages
            $('#ageError').html('');
            $('#heightError').html('');
            $('#weightError').html('');
            $('#descriptionError').html('');
            $('#nameError').html('');
            $('#genderError').html('');
            $('#orientationError').html('');
            $('#countryError').html('');
            $('#poolError').html('');
            $('#plzError').html('');

            // countryID = selected countryID or profile's countryID
            var countryID = angular.isDefined($scope.country)
                ? $scope.country.selected
                : $scope.profile.countryID;

            // poolID = selected poolID or profile's poolID
            var poolID = angular.isDefined($scope.pool)
                ? $scope.pool.selected
                : $scope.profile.poolID;

            // fields validation
            if ( !$("#name").val() || ! /^.{2,20}$/.test($("#name").val()) ) {
                $('#nameError').html(' * Allowed length 3 to 20 characters.').css("color", "red");
                e.preventDefault();
            }
            if (!$("#age").val() || $("#age").val() < 18) {
                $('#ageError').html(' is not a correct value (18+)').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (!$("#height").val() || $("#height").val() == 0) {
                $('#heightError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (!$("#weight").val() || $("#weight").val() == 0) {
                $('#weightError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (!$("#gender").val() || !/^[mMfF]$/.test(jQuery("#gender").val())) {
                $('#genderError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (!$("#orientation").val() || !/^[mMfFbB]$/.test(jQuery("#orientation").val())) {
                $('#orientationError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (!$("#plz").val()) {
                $('#plzError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (angular.isUndefined(countryID)) {
                $('#countryError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if (angular.isUndefined(poolID)) {
                $('#poolError').html(' is not a correct value').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            if ($("#description").val().length > 495) {
                $('#descriptionError').html(' '+($("#description").val().length + 5)+' char : Maximum 500 characters').css("color", "red");
                e.preventDefault();e.stopPropagation();
            }
            else {
                //$('#profile-form').submit();
                //e.preventDefault();e.stopPropagation();
            }
            //e.preventDefault(); // ajax is async, no submit form here, but after ajax response and inputs validation (above code)
        };

        $scope.emptyChatForm = function(mobileID){

            $scope.switchViews.mobileID = mobileID;
            $('#selected-name').val('');
            $('#fake-profileID').val('');
            $scope.messages = [];
            $scope.validateForm2();
            $scope.switchViews.showUsers = false;
            $scope.switchViews.showChats = true;
            $scope.switchViews.showResult = true;
            $scope.switchViews.showForm = false;
        };

        $scope.validateForm2 = function(e){

            $('#nameError2').html('');
            $('#msisdnError').html('');
            $('#imsiError').html('');
            $('#mobileIDError').html('');
            $('#msisdn_longError').html('');
            $('#customerNameError').html('');

            // submitting a long MSISDN
            if ($scope.toggle.toggling || !$scope.user.session.admin) {

                if (!$("#msisdn_long").val()) {
                    $("#msisdn_longError").html('Bitte füllen Sie das Eingabefeld aus');
                    e.preventDefault();
                }

                else if ($("#msisdn_long").val() && !($("#msisdn_long").val().match(/^(?:\+|00|)([1-9]{1}[0-9]{14,20})$/))) {
                    $('#msisdn_longError').html('Verlängte msisdn format not supported. e.g: 491601234567000001');
                    e.preventDefault();
                }

                else{

                    // switch div views
                    $scope.selectedMobile.showing = true;
                    $scope.mobile.showing = false;
                    $scope.checkbox.showing = false;
                    $scope.toggle.toggling = !$scope.toggle.toggling;

                    $http({
                        method: 'POST',
                        url: $scope.user.session.ajaxurl,
                        data: { msisdn_long: $("#msisdn_long").val() }
                    }).then(function successCallback(response) {
                        $scope.messages = response.data;
                        $scope.messages.length = Object.keys($scope.messages.data).length -1;
                        $scope.msisdn_long = $scope.messages.data[$scope.messages.length][0];
                        $scope.profileName = $scope.messages.data[$scope.messages.length][1];
                        $scope.msisdn = $scope.messages.data[$scope.messages.length][2];
                        $scope.chatter = $scope.messages.data[$scope.messages.length][3];

                    }, function errorCallback(response) {
                        // called asynchronously if an error occurs or server returns response with an error status.
                        $('#msisdn_longError').html('Profile or mobile not found');
                    });
                    e.preventDefault();
                }
            }

            // submitting a profile or a mobile or a couple profile/mobile
            else {

                // submitting an empty form
                if (!$("#customerName").val() && !$("#selected-name").val() && !$("#msisdn").val() && !$("#imsi").val() && !$("#mobileID").val()) {
                    if(e) e.preventDefault();
                }

                // submitting a fake profileID only
                if ($("#fake-profileID").val() && !($("#msisdn").val() || $("#imsi").val() || $("#mobileID").val() || Object.keys($scope.customer).length > 0)) {

                    // Get list of senders msisdn for the selected profileID
                    $http({
                        method: 'POST',
                        url: $scope.user.session.ajaxurl,
                        data: { fakeProfileID: $("#fake-profileID").val() }
                    }).then(function successCallback(response) {

                        $scope.msisdns = response.data;

                    }, function errorCallback(response) {
                        $('#nameError2').html('No result.');
                    });
                }

                // submitting a customer data only (msisdn or imsi or mobileID or customer name)
                if (!$("#fake-profileID").val() && ($("#customerName").val() || $("#msisdn").val() || $("#imsi").val() || $("#mobileID").val() || $scope.switchViews.mobileID)){

                    // Invalid customer name
                    if ( $("#customerName").val() && ! /^.{2,20}$/.test($("#customerName").val()) ) {
                        $('#customerNameError')
                            .html(' Only 3 to 20 letters, numbers, underscore (_) or hyphen (-).')
                            .css("color", "red");
                        e.preventDefault();
                    }

                    // Get customer and its chats by customer name
                    if($("#customerName").val()){

                        // Get customer and its chats profiles
                        ajaxService.fn(

                            // get customer
                            $scope.user.session.ajaxurl,    // URL

                            { 'customerName': $("#customerName").val() },     // Parameters

                            function (response) {           // Callback function

                                if(response.status == 200){

                                    // assign customer in scope
                                    $scope.customer = response.data;

                                    // load chats from the customers
                                    $http({
                                      method: 'POST',
                                      url: $scope.user.session.ajaxurl,

                                      data: { mobileID: $scope.customer.mobileID }

                                    }).then(function successCallback(response){

                                        $scope.chatProfiles = response.data;

                                        // Hide divs
                                        $scope.profileSelect.showing = false;
                                        $scope.mobile.showing = false;
                                        $scope.checkbox.showing = false;

                                        //Show divs
                                        $("#messages-container").show();
                                        $("#chat").show();
                                        $scope.chatProfilesShowing = true;
                                        $scope.selectedMobile.showing = true;
                                        $scope.selectedMobile.showing = true;
                                        $scope.showCustomer = true;

                                        // on error
                                      }, function errorCallback(response) {
                                        // called asynchronously if an error occurs or server returns response with an error status.
                                        $scope.profileSelect.showing = false;
                                        $('#msisdnError').html(' Mobile not found');
                                        $('#nameError2').html('');
                                        $scope.selectedMobile.showing = false;
                                      });

                                } else {
                                    $scope.mobile.showing = true;
                                    $('#customerNameError').html(' Customer not found ').css("color", "red");
                                    e.preventDefault();
                                }
                            }
                        );
                    }

                    // Invalid MSISDN
                    if( $("#msisdn").val() && !($("#msisdn").val().match(/^(?:\+|00|)([1-9]{1}[0-9]{5,14})$/)) ){
                        $('#msisdnError').html(' msisdn format not supported. e.g: 491601234567');
                        $scope.profileSelect.showing = false;
                        $('#nameError2').html('');
                        e.preventDefault();
                    }

                    // Invalid IMSI
                    else if($("#imsi").val() && !($("#imsi").val().match(/^[1-9]{1}[0-9]{5,15}$/))){
                        $('#imsiError').html(' imsi format not supported. e.g: 262026045457418');
                        $scope.profileSelect.showing = false;
                        $('#nameError2').html('');
                        e.preventDefault();
                    }

                    // Invalid mobileID
                    else if($("#mobileID").val() && ($("#mobileID").val() > 4294967295 || $("#mobileID").val() < 1)){
                        $('#mobileIDError').html(' incorrect value (between 1 and 4294967295)');
                        $scope.profileSelect.showing = false;
                        $('#nameError2').html('');
                        e.preventDefault();
                    }

                    // Get customer and its chats by mobile data
                    else {

                        if($('#msisdn').val()){
                            $scope.selectedMobile.msisdn = $('#msisdn').val();
                        }

                        else if($('#imsi').val()){
                            $scope.selectedMobile.msisdn = $('#imsi').val();
                        }

                        else if($scope.switchViews.mobileID){
                            $("#mobileID").val($scope.switchViews.mobileID);
                        }

                        else{
                            $scope.selectedMobile.msisdn = $('#mobileID').val();
                        }

                        $http({
                          method: 'POST',
                          url: $scope.user.session.ajaxurl,
                          data: { msisdn: $("#msisdn").val(), imsi: $("#imsi").val(), mobileID: $("#mobileID").val() }
                        }).then(function successCallback(response){

                            $scope.chatProfiles = response.data;

                            // assign msisdn variable
                            $scope.msisdn = response.data[response.data.length - 1];

                            // assign customer variable
                            $scope.customer = response.data[response.data.length - 2];

                            // unset msisdn from profiles array
                            $scope.chatProfiles.splice($scope.chatProfiles.length - 1, 1);

                            // unset customer from profiles array
                            $scope.chatProfiles.splice($scope.chatProfiles.length - 1, 1);

                            // Hide divs
                            $scope.profileSelect.showing = false;
                            $scope.mobile.showing = false;
                            $scope.checkbox.showing = false;

                            //Show divs
                            $scope.chatProfilesShowing = true;
                            $scope.selectedMobile.showing = true;
                            $scope.showCustomer = true;

                            // on error
                          }, function errorCallback(response) {
                            // called asynchronously if an error occurs or server returns response with an error status.
                            $scope.profileSelect.showing = false;
                            $('#msisdnError').html(' Mobile not found');
                            $('#nameError2').html('');
                            $scope.selectedMobile.showing = false;
                          });

                        if(e) e.preventDefault();
                    }
                }

                // submitting a fake-profile AND a mobile data (msisdn or imsi or mobileID)
                if ($("#fake-profileID").val() && ($("#msisdn").val() || $("#imsi").val() || $("#mobileID").val() || Object.keys($scope.customer).length > 0 )){

                    // switch div views
                    $scope.selectedMobile.showing = true;
                    $scope.mobile.showing = false;
                    $scope.checkbox.showing = false;

                    // get Chat
                    $http({
                        method: 'POST',
                        url: $scope.user.session.ajaxurl,
                        data: { fakeProfileID: $("#fake-profileID").val(), msisdn: $('#msisdn').val(), imsi: $('#imsi').val(), mobileID: $('#mobileID').val() ? $('#mobileID').val() : $scope.customer.mobileID }
                    })
                    .then(function successCallback(response) {

                        $scope.messages = response.data;
                        $scope.messages.length = Object.keys($scope.messages.data).length -1;
                        $scope.msisdn_long = $scope.messages.data[$scope.messages.length][0];
                        $scope.profileName = $scope.messages.data[$scope.messages.length][1];
                        $scope.msisdn = $scope.messages.data[$scope.messages.length][2];
                        $scope.userName = $scope.messages.data[$scope.messages.length][3];

                    }, function errorCallback(response) {
                        // called asynchronously if an error occurs or server returns response with an error status.
                        //$('#nameError2').html(' Profile not found');
                    });
                    if(e) e.preventDefault();
                }
            }
        };

        // function: get customer by ID
        $scope.getCustomer = function (profileID) {

            ajaxService.fn(
                $scope.user.session.ajaxurl,    // URL
                { 'profileID': profileID },     // Parameters
                function (response) {           // Callback function
                    if(response.status == 200){
                        $scope.customer = response.data;
                        $scope.customer.length = Object.keys($scope.customer).length -1;
                    } else {
                        console.log('could not load customer');
                    }
                }
            );
        };

        // function: get customer by name
        $scope.getCustomerByName = function (name) {

            ajaxService.fn(
                $scope.user.session.ajaxurl,    // URL
                { 'customerName': name },     // Parameters
                function (response) {           // Callback function
                    if(response.status == 200){
                        $scope.customer = response.data;
                        $scope.customer.length = Object.keys($scope.customer).length -1;
                    } else {
                        console.log('could not load customer');
                    }
                }
            );
        };

        $scope.onSelectedProfile = function (selectedItem, selectedID, e) {

            // show chats
            $("#chat").show();
            $("#messages-container").show();

            // hide form
            $scope.numbers.showing = false;

            // fill formular with selected profile (name and ID)
            $('#selected-name').val(selectedItem);
            $('#fake-profileID').val(selectedID);

            $scope.validateForm2(e);
        };

        $scope.onSelectSelectedProfile = function (selectedName, selectedID) {

            // switch views
            $scope.switchViews.showResult = true;
            $scope.switchViews.showForm = false;

            // save fake profile name in scope to use in error message to user
            $scope.fakeProfile = {
                profileName : selectedName
            };

            // fill formular hidden input field with selected profileID
            $('#fake-profileID').val(selectedID);

            $scope.validateForm2();
        };

        $scope.onSelectedUser = function (selectedUser, e) {

            // switch views
            $scope.switchViews.showResult = true;
            $scope.switchViews.showForm = false;
            $scope.switchViews.mobileID = '';
            $scope.messages = [];

            $scope.getCustomer(selectedUser.profileID);

            $('#mobileID').val(selectedUser.mobileID);

            $scope.validateForm2(e);
        };

        $scope.onSelectedMsisdn = function (selectedItem, selectedID) {

            $("#chat").show();
            $("#messages-container").show();
            $('#msisdn').val(selectedItem);
            $('#mobileID').val(selectedID);

            $scope.validateForm2();
        };

        $scope.deleteImage = function(imageId, profileID){

            // prepare ajax data
            var ajaxData = {
                'deleteImage': {
                    'imageID': imageId,
                    'profileID': profileID
                    }
            };
            ajaxService.fn(
                $scope.user.session.ajaxurl,
                ajaxData,
                function (response) {

                    if(response.status == 200){

                        // display flash message
                        $('#alert').html('image deleted !').css("color","#47a447").css("top","20px");

                        // fade and remove flash message
                        setTimeout(function(){
                            $('#alert').html(' &nbsp');
                            },5000);

                        // update profile
                        $scope.customer = $scope.getCustomer(profileID);

                    } else {

                        // display flash message
                        $('#alert').html('Could not delete image !').css("color","red").css("top","20px");

                        // fade and remove flash message
                        setTimeout(function(){
                            $('#alert').html(' &nbsp');
                            },5000);

                    }
                }
            );
        };

        $scope.getMOs = function(){
            var values = $scope.messages.data;
            var MOs = 0;
            angular.forEach(values, function(value, key) {
                if(value.from == 1) MOs++;
            });
            return MOs;
        };

        $scope.resetChatForm = function(e) {

            // empty Form
            $('html, body').animate({ scrollTop: 0 }, 'fast');
            $("#selected-name").val('');
            $("#customerName").val('');
            $("#fake-profileID").val('');
            $("#msisdn").val('');
            $("#imsi").val('');
            $("#mobileID").val('');
            $("#messages-container").hide();

            // empty scope variables
            $scope.customer = {};
            $scope.messages = {};

            // switch views
            $scope.switchViews.showForm = true;
            $scope.switchViews.showResult = false;
        };

        $scope.filterProfiles = function(){

            // reinitialize profiles list
            $scope.profiles = angular.copy($scope.backUpProfiles);

            if(!angular.isUndefined($scope.poolSelection)){

                if($scope.poolSelection.selected.poolID != 0){

                    if(!angular.isUndefined($scope.profiles)){

                        for (var i = $scope.profiles.length - 1; i >= 0; i--) {

                            // filter profiles by poolID
                            if(!angular.isUndefined($scope.profiles[i])){

                                if ($scope.profiles[i].poolID != $scope.poolSelection.selected.poolID ) {

                                    $scope.profiles.splice(i, 1);
                                }
                            }
                        }
                    }
                }
            }
        };

        $scope.filterHiddenProfiles = function(){

            // reinitialize profiles list
            $scope.hiddenProfiles = angular.copy($scope.backUpHiddenProfiles);

            if(!angular.isUndefined($scope.backUpHiddenProfiles)){

                for (var i = $scope.hiddenProfiles.length - 1; i >= 0; i--) {

                    // filter profiles by poolID
                    if(!angular.isUndefined($scope.poolSelectionHidden)){

                        if(!angular.isUndefined($scope.poolSelectionHidden.selected) && !angular.isUndefined($scope.hiddenProfiles[i])){

                            if ($scope.poolSelectionHidden.selected.poolID != 0 && $scope.hiddenProfiles[i].poolID != $scope.poolSelectionHidden.selected.poolID) {

                                $scope.hiddenProfiles.splice(i, 1);
                            }
                        }
                    }
                }
            }
        };

        $scope.getTop50Users = function(e){

            var active = $scope.top50activity.selected.period == 'All users' ? 1 : 2;

            // Loading animation
            $scope.top50usersloader = { loading: true };

            // Ajax request
            $http({
                method: 'POST',
                url: $scope.user.session.ajaxurl,
                data: { top50users: active, from: $scope.dateTop50.from, to: $scope.dateTop50.to }
            }).then(function successCallback(response) {
                $scope.top50Users = response.data;
                $scope.top50usersloader.loading = false;
            }, function errorCallback(response) {
                // called asynchronously if an error occurs or server returns response with an error status.
                console.log("call to getTop50Users() returns an error"+response);
                $scope.top50usersloader.loading = false;
            });
        };

        $scope.validateStatsForm = function(e){

            // prepare params for POST
            var active = $scope.activity.selected.name == 'aktiv' ? 1 : 0;
            var period = $scope.periods.selected.amount;
            var amount_MOs = $scope.amount_MOs.selected.amount.match(/[0-9]{1,3}/g); // returns an array from all occurences 0 to 999 (The g modifier is used to perform a global match (find all matches rather than stopping after the first match).)

            // text "Loading..." till get data
            $scope.statsloader = { loading: true };

            $http({
                method: 'POST',
                url: $scope.user.session.ajaxurl,
                data: { active: active, period: period, amount: amount_MOs, from: $scope.dateUsersStats.from, to: $scope.dateUsersStats.to  }
            }).then(function successCallback(response) {
                $scope.userstats = response.data;
                $scope.userstats.lgth = $scope.userstats.length ? Object.keys($scope.userstats).length : 'No';
                $scope.statsloader.loading = false;
            }, function errorCallback(response) {
                // called asynchronously if an error occurs or server returns response with an error status.
                console.log("call to getUsersStats() returns an error"+response);
                $scope.statsloader.loading = false;
            });

            e.preventDefault();
        };

        $scope.getProfilesStats = function(force){

            // load stats if not defined
            if(angular.isUndefined($scope.jstats) || !angular.isUndefined(force)){

                // initialize datepicker variables
                if(angular.isUndefined($scope.dateProfiles)){
                    $scope.dateProfiles = {
                        from: new Date(),
                        to: new Date()
                    };
                    var from = new Date().setDate($scope.dateProfiles.from.getDate()-30);
                    $scope.dateProfiles.from = new Date(from);
                    }

                // loader
                $('#profiles-loader').addClass('loader').css({ right: '50%' }).css({ top: '10%' });

                // prepare ajax data
                var ajaxData = {
                    'profiles_stats': {
                        'from': $scope.dateProfiles.from,
                        'to': $scope.dateProfiles.to
                        }
                };

                ajaxService.fn(
                    $scope.user.session.ajaxurl,
                    ajaxData,
                    function (response) {
                        if(response.status == 200){

                            // remove "Loading ..."
                            $('#profiles-loader').removeClass('loader').removeAttr('style');

                            $scope.jstats = response.data; // JSON is automatically converted in JS object by AngularJS http service

                            // copy for filtering
                            $scope.backUpJstats = angular.copy($scope.jstats);

                            // sort by selected pool
                            $scope.filterProfilesStats();

                        } else {

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');
                            console.log('could not load profiles stats');
                        }
                    }
                );
            }

            else{

                // sort by selected pool
                $scope.filterProfilesStats();
            }
        };

        $scope.filterProfilesStats = function(){

            // reinitialize list
            $scope.jstats = angular.copy($scope.backUpJstats);

            if(!angular.isUndefined($scope.poolSelection)){

                if($scope.poolSelection.selected.poolID != 0){

                    if(!angular.isUndefined($scope.jstats)){

                        for (var i = $scope.jstats.length - 1; i >= 0; i--) {

                            // filter profiles by poolID
                            if(!angular.isUndefined($scope.jstats[i])){

                                if ($scope.jstats[i].poolID != $scope.poolSelection.selected.poolID ) {

                                    $scope.jstats.splice(i, 1);
                                }
                            }
                        }

                        $scope.profilesCharts();
                    }
                }
            }
        };

        $scope.profilesCharts = function(e){

            // build charts series
            $scope.categories = new Array();
            $scope.series =  [
                { name: "Erhaltene MOs", data: [] },
                { name: "Senders", data:[], color: '#EF00ED' },
                { name: "Mittel MOs pro chat", data:[], color: '#90ed7d' }
            ];

            for(var i= 0; i < $scope.jstats.length; i++) {
                $scope.categories.push($scope.jstats[i].profileName);
                $scope.series[0].data.push($scope.jstats[i].messages_received);
                $scope.series[1].data.push($scope.jstats[i].senders);
                $scope.series[2].data.push(Math.round($scope.jstats[i].messages_received/$scope.jstats[i].senders));
            }

            var chart = $('#container1').highcharts(
                            {
                                title: {
                                    text: 'Nachrichten pro Profil',
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
                                    type: 'column',
                                    backgroundColor:'#3e3e3e',
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
                                    categories: $scope.categories,
                                },
                                yAxis: {
                                    min: 0,
                                    title: {
                                        text: '',
                                        style: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        }
                                    },
                                    labels: {
                                        style: {
                                            color:"rgb(189, 193, 195)",
                                        }
                                    },
                                },
                                tooltip: {
                                    headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                                    pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}&nbsp;&nbsp;</td>' +
                                        '<td style="padding:0"><b>{point.y}</b></td></tr>',
                                    footerFormat: '</table>',
                                    shared: true,
                                    useHTML: true
                                },

                                plotOptions: {
                                    series: {
                                        borderWidth: 0
                                    }
                                },

                                series: $scope.series
                            }
                        );
        };

        $scope.heavyUsersStats = function(e){

            // Load heavy users stats if not defined
            //if(angular.isUndefined($scope.heavyUsersSeries)){

                // initialize datepicker variables
                if(angular.isUndefined($scope.dateHeavyUsers)){
                    $scope.dateHeavyUsers = {
                        from: new Date(),
                        to: new Date()
                    };
                    var from = new Date().setDate($scope.dateHeavyUsers.from.getDate()-30);
                    $scope.dateHeavyUsers.from = new Date(from);
                    }

                // display message "Loading profiles..." in navbar
                $('#alert').html('Wird geladen ...').css("color","#47a447");

                // prepare ajax data
                var ajaxData = {
                    'heavy_users': {
                        'from': $scope.dateHeavyUsers.from,
                        'to': $scope.dateHeavyUsers.to
                        }
                };

                ajaxService.fn(
                    $scope.user.session.ajaxurl,
                    ajaxData,
                    function (response) {
                        if(response.status == 200){

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');

                            // cast obj in array
                            const values = Object.values(response.data);

                            $scope.heavyUsersDates = new Array();
                            $scope.heavyUsersSeries =  [
                                { name: "MOs", data: [], color: '#90ed7d' },
                                { name: "Users", data:[], color: '#EF00ED' }
                            ];

                            for(var i= 0; i < values.length; i++) {

                                $scope.heavyUsersDates.push(values[i].name);
                                $scope.heavyUsersSeries[0].data.push(values[i].MOs);
                                $scope.heavyUsersSeries[1].data.push(values[i].users);
                            }

                            var chart = $('#heavy-charts').highcharts(
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
                                        color: "rgb(189, 193, 195)"
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
                                        categories: $scope.heavyUsersDates,
                                    },
                                    yAxis: [
                                        {
                                            lineWidth: 1,
                                            title: {
                                                text: 'MOs',
                                                style: {
                                                    color: 'rgb(247, 163, 92)',
                                                    fontSize: '15px'
                                                }
                                            },
                                            labels: {
                                                style: {
                                                    color: 'rgb(247, 163, 92)'
                                                }
                                            }
                                        },
                                        {
                                            lineWidth: 1,
                                            opposite: true,
                                            title: {
                                                text: 'Users',
                                                style: {
                                                    color: 'mediumspringgreen',
                                                    fontSize: '15px'
                                                }
                                            },
                                            labels: {
                                                style: {
                                                    color: 'mediumspringgreen'
                                                }
                                            }
                                        }
                                    ],
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
                                    series: [
                                        {
                                            data: $scope.heavyUsersSeries[0].data,
                                            color: 'rgb(247, 163, 92)',
                                            name: 'MOs'
                                        },
                                        {
                                            data: $scope.heavyUsersSeries[1].data,
                                            yAxis: 1,
                                            color: 'mediumspringgreen',
                                            name: 'Users'
                                        }
                                    ],
                                }
                            ); // end .highcharts

                        } else {

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');
                            console.log('could not load heavy_users');
                        }
                    }
                );
            //}
        };

        // Highmap (Highcharts) - World and Europa
        $scope.getMOsByCountries = function(e){

            // Load MOs by countries if not defined
            if(angular.isUndefined($scope.MOsByCountries)){

                // initialize datepicker variables
                if(angular.isUndefined($scope.dateEurope)){
                    $scope.dateEurope = {
                        from: new Date(),
                        to: new Date()
                    };
                    var from = new Date().setDate($scope.dateEurope.from.getDate()-30);
                    $scope.dateEurope.from = new Date(from);
                    }

                // display message "Loading profiles..." in navbar
                $('#alert').html('Wird geladen ...').css("color","#47a447");

                    // prepare ajax data
                var ajaxData = {
                    'MOsByCountries': {
                        'from': $scope.dateEurope.from,
                        'to': $scope.dateEurope.to
                        }
                };

                ajaxService.fn(
                    $scope.user.session.ajaxurl,    // URL
                    ajaxData,                    // Parameters
                    function (response) {           // Callback function

                        if(response.status == 200) {

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');

                            $scope.MOsByCountries = response.data;

                            var data = [];

                            for (var i = $scope.MOsByCountries.length - 1; i >= 0; i--) {
                                for (var j = $scope.countries.length - 1; j >= 0; j--) {
                                    if(!angular.isUndefined($scope.MOsByCountries[i]) && $scope.MOsByCountries[i].countryID == $scope.countries[j].countryID){
                                        //$scope.MOsByCountries[i] = [$scope.countries[j].code, $scope.countries[j].mos];

                                        // format england from UK to GB
                                        if($scope.countries[j].code == "uk") $scope.countries[j].code = "gb";

                                        var el = [$scope.countries[j].code, $scope.MOsByCountries[i].mos];
                                        data[i] = el;
                                    }
                                }
                            }

                            console.log(data);
                            console.log(data.slice());

                            // Create the World chart
                            Highcharts.mapChart('geo-MOs', {
                                chart: {
                                    map: 'custom/world-palestine-highres',
                                    backgroundColor:'rgba(0,0,0,0)',
                                    height: 900
                                },
                                title: {
                                    text: '',
                                    style: {
                                        color: 'rgb(189, 193, 195)',
                                        font: ''
                                    }
                                },
                                subtitle: {
                                    text: ''
                                },
                                mapNavigation: {
                                    enabled: true,
                                    buttonOptions: {
                                        verticalAlign: 'bottom'
                                    }
                                },
                                colorAxis: {
                                    min: 1,
                                    type: 'logarithmic',
                                    minColor: '#EEEEFF',
                                    maxColor: '#000022',
                                    stops: [
                                        [0, '#EFEFFF'],
                                        [0.67, '#e75012'],
                                        [1, '#000022']
                                    ]
                                },
                                series: [{
                                    data: data.slice(), // copy
                                    name: 'MOs',
                                    color: '#E0E0E0',
                                    states: {
                                        hover: {
                                            color: '#428bca',
                                            borderColor: 'gray'
                                        }
                                    },
                                    dataLabels: {
                                        enabled: true,
                                        format: '{point.name}'
                                    }
                                }]
                            });

                            // Create the Europe chart
                            Highcharts.mapChart('geo-world-MOs', {
                                chart: {
                                    map: 'custom/europe',
                                    backgroundColor:'rgba(0,0,0,0)',
                                    height: 900
                                },
                                title: {
                                    text: ''
                                },
                                subtitle: {
                                    text: ''
                                },
                                mapNavigation: {
                                    enabled: true,
                                    buttonOptions: {
                                        verticalAlign: 'bottom'
                                    }
                                },
                                colorAxis: {
                                    min: 1,
                                    type: 'logarithmic',
                                    minColor: '#EEEEFF',
                                    maxColor: '#000022',
                                    stops: [
                                        [0, '#EFEFFF'],
                                        [0.67, '#e75012'],
                                        [1, '#000022']
                                    ]
                                },
                                series: [{
                                    data: data.slice(),
                                    name: 'MOs',
                                    color: '#E0E0E0',
                                    states: {
                                        hover: {
                                            color: '#428bca',
                                            borderColor: 'gray'
                                        }
                                    },
                                    dataLabels: {
                                        enabled: true,
                                        format: '{point.name}'
                                    }
                                }]
                            });

                        } else {

                            // remove message "Loading profiles..." from navbar
                            $('#alert').html('');
                            console.log('could not load MOsByCountries');
                            $scope.pools = {};
                        }
                    }
                );
            }
        };

        /* Users clustering */
        $scope.getClustering = function(e){

            // Load clustering if not defined
            if(angular.isUndefined($scope.clustered)){

                // text "Loading..." during get data
                $scope.clusteringloader = { loading: true };

                ajaxService.fn(
                    $scope.user.session.ajaxurl,      // URL
                    {'clustering':$scope.clustering}, // Parameters
                    function (response) {             // Callback function
                        if(response.status == 200){

                            $scope.clustered = response.data;

                            $scope.clusteringSeries = [];
                            $scope.clusteringSeriesbis = [];

                            for(var i= 0; i < $scope.clustered.length; i++){
                                $scope.clusteringSeries.push({'name':$scope.clustered[i][0].range, 'y':$scope.clustered[i][0].users});
                                $scope.clusteringSeriesbis.push({'name':$scope.clustered[i][0].range, 'y': parseInt($scope.clustered[i][0].MOs)});
                            }

                            var chart = $('#clustering-charts').highcharts(
                                {
                                    title: {
                                        text: '',
                                        style: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        }
                                    },
                                    legend: {
                                        title: {
                                            style: {'color':'lightgrey'},
                                            text: 'MOs'
                                        },
                                        itemStyle: {
                                            color: 'rgb(189, 193, 195)',
                                            font: ''
                                        },
                                        x: -35,
                                        y: -200
                                    },
                                    chart: { //http://www.highcharts.com/docs/chart-design-and-style/design-and-style
                                        type: 'pie',
                                        backgroundColor:'#3e3e3e',
                                        color: "rgb(189, 193, 195)",
                                        spacingTop: 50,
                                        spacingLeft: 0,
                                        spacingRight: 0,
                                        spacingBottom: -200,
                                        height: 218
                                    },
                                    tooltip: {
                                        headerFormat: '<span style="font-size:11px">Range:&nbsp;{point.key} MOs</span><table>',
                                        pointFormat: '<tr><td style="color:{series.color};padding:0">{point.percentage:.1f}%&nbsp;&nbsp;</td>' +
                                            '<td style="padding:0"><b>{point.y}&nbsp;</b></td></tr>',
                                        footerFormat: '</table>',
                                        shared: true,
                                        useHTML: true
                                    },
                                    plotOptions: {
                                        pie: {
                                            allowPointSelect: true,
                                            cursor: 'pointer',
                                            dataLabels: {
                                                distance:0,
                                                enabled: true,
                                                format: '<b>{point.name} MOs</b>: {point.percentage:.1f} %',
                                                style: {
                                                    color:"rgb(189, 193, 195)",
                                                    fontSize: "12px",
                                                    fontWeight: "normal",
                                                    textShadow: ""
                                                }
                                            },
                                            showInLegend: true
                                        }
                                    },
                                    series: [
                                        {
                                            type: 'pie',
                                            title: 'Users clustering',
                                            name: 'Users clustering',
                                            center: [200, null],
                                            size: 130,
                                            dataLabels: {
                                                enabled: false
                                            },
                                            showInLegend: true,
                                            data: $scope.clusteringSeries
                                        },
                                        {
                                            type: 'pie',
                                            name: 'name2: MOs clustering',
                                            center: [500, null],
                                            size: 130,
                                            dataLabels: {
                                            enabled: false
                                            },
                                            showInLegend: true,
                                            data: $scope.clusteringSeriesbis
                                        }
                                    ]
                                },
                                function(chart){
                                    var datalabel;
                                    //chart.series[0].data[1].dataLabel.translate(datalabel.x,data.plotY-20);
                                    $.each(chart.series[0].data,function(j,data){
                                        //if(data.y < 0)
                                        {
                                            datalabel = data.dataLabel;
                                            //datalabel.translate(datalabel.x,data.y+1);
                                        }
                                    });
                                }
                            ); // end .highcharts

                            $scope.clusteringloader.loading = false;
                        }
                        else {
                            $scope.clusteringloader.loading = false;
                        }
                    }
                );
            }
        };

        /* Aktivitätsdauer */
        $scope.validateLifeTimeForm = function(e){

            // get values from DOM vs. from angular directive datepicker
            //console.log($('#dateFrom').val());
            //console.log($('#dateTo').val());

            // Set default values:
            // from = first day of current month
            // to = today
            if($scope.lifetimeDates.dateFrom == 0) {
                $scope.lifetimeDates.dateFrom = new Date().getFullYear() + '-' + ((new Date().getMonth() + 1) < 10 ? '0' : '') + (new Date().getMonth() + 1) + '-01';
            }
            if($scope.lifetimeDates.dateTo == 0) {
                $scope.lifetimeDates.dateTo = new Date().toJSON().substr(0, 10);
            }

            //console.log($scope.lifetimeDates.dateFrom);
            //console.log($scope.lifetimeDates.dateTo);

            // text "Loading..." during get data
            $scope.lifetimeloader = { loading: true };

            $http({
                method: 'POST',
                url: $scope.user.session.ajaxurl,
                data: {
                    lifetime: $scope.life.selected.users,
                    from: $scope.lifetimeDates.dateFrom + ' 00:00:00.000000',
                    to: $scope.lifetimeDates.dateTo + ' 00:00:00.000000'
                }
            }).then(function successCallback(response) {
                $scope.lifetimestats = response.data.replace(/"/g, '');
                $scope.lifetimeloader.loading = false;
                // Add in view
                $("#date-from").html($scope.lifetimeDates.dateFrom);
                $("#date-to").html($scope.lifetimeDates.dateTo);
                // style
                $("#date-from").css('font-weight', 'bold');
                $("#date-to").css('font-weight', 'bold');
                $("#date-from").css('color', '#ff9811');
                $("#date-to").css('color', '#ff9811');
            }, function errorCallback(response) {
                // called asynchronously if an error occurs or server returns response with an error status.
                 $scope.lifetimeloader.loading = false;
            });

            e.preventDefault();
        };

        // User password encryption
        $scope.encryptPassword = function(e){

            $('#usernameError').html('');
            if(!$("#username").val()) {
                $("#usernameError").html('Bitte füllen Sie alle Felder aus');
                e.preventDefault();
            }

            if($("#username").val() && !($("#username").val().match(/^.{2,20}$/))){

                $('#usernameError').html(' only (A-Z,a-z,0-9), underscore (_) or hyphen (-)');
                e.preventDefault();
            }

         // SHA1 encryption tool
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
                   i = str.charCodeAt(str_len - 3) << 24 | str.charCodeAt(str_len - 2) << 16 | str.charCodeAt(str_len - 1) <<
                   8 | 0x80;
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

         var txt_string = document.getElementById('login_password').value;

         document.getElementById('login_password').value = tool.sha1(txt_string);
        };

        $scope.copyToClipboard = function(i, name){

            var urlField = document.querySelector('#url-field'+name+i);
            // select the contents
            urlField.select();

            document.execCommand('copy'); // or 'cut'
        };

        // Add style to clicked element
        $scope.isClicked = function(id){

            $("#"+id).text('Der Link steht in Zwischenablage!');

            // 1. need to use attr() instead of css() because of "!important"
            // 2. normal behaviour of attr('style') is to delete any previous css style,
            //    so all style attributes should be defined at ones in a string
            $("#"+id).attr('style', 'color:black !important; background-color:#428bca !important;');

            /*
                In case the previous code does not work (IE6 or other browsers),
                or it is needed to add more attrs, i.e. attr('style', 'string1', 'class', 'string2'):

                This map() function should transform an object like this:

                {
                    'color': 'black !important',
                    'background-color': '#428bca !important'
                }

                In a string like that:

                'color:black !important;background-color:#428bca !important;'
            */
            function map(map) {
                var cssValue = [];

                for (var o in map) {
                    cssValue.push(o + ':' + map[o] + ';')
                }

                // array to string
                return cssValue.join('');
            }

            // change style after 4 sec
            setTimeout(
                function(){
                    $("#"+id).text('Kopiere Link in Zwischenablage');
                    //$("#"+id).attr('style', 'color: green !important');
                    $("#"+id).attr('style', 'background-color: black !important');
                },
                4000
            );
        };

        $scope.updatePool = function(id, name, portal){

            // not allowed in demo mode
            if(!$scope.user.session.demo){

                // prepare ajax data
                var ajaxData = {
                    'updatePool': {
                        'poolID': id,
                        'name': name,
                        'portal_domain': portal
                        }
                };

                ajaxService.fn(
                    $scope.user.session.ajaxurl,
                    ajaxData,
                    function (response) {
                        if(response.status == 200){

                            // display flash message
                            $('#alert').html('Pool Updated !').css("color","#47a447");

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').html(' &nbsp');
                                },5000);

                        } else {
                            console.log('could not update pool');
                        }
                    }
                );
            }
        };

        $scope.createPool = function(name, id, scheme, portalDomain, e){

            $("#create-pool-error").html('&nbsp;');

            // Validate params
            if(angular.isUndefined(name) || ! /^.{1,20}$/.test(name) ){
                $("#create-pool-error").css('color', '#ff9811').html('&nbsp;invalid name');
            }
            else if(angular.isUndefined(id) || ! /^.{1,20}$/.test(id) ){
                $("#create-pool-error").css('color', '#ff9811').html('&nbsp;invalid countryID');
            }
            else if(angular.isUndefined(scheme) || ! /^.{1,20}$/.test(scheme) ){
                $("#create-pool-error").css('color', '#ff9811').html('&nbsp;invalid scheme');
            }
            else if(angular.isUndefined(portalDomain) || ! /^.{1,30}$/.test(portalDomain) ){
                $("#create-pool-error").css('color', '#ff9811').html('&nbsp;invalid portalDomain');
            }

            // Ajax request
            else{

                // not allowed in demo mode
                if(!$scope.user.session.demo){

                    // prepare ajax data
                    var ajaxData = {
                        'createPool': {
                            'name': name,
                            'countryID': id,
                            'scheme': scheme,
                            'portal_domain': portalDomain
                            }
                    };

                    ajaxService.fn(
                        $scope.user.session.ajaxurl,
                        ajaxData,
                        function (response) {
                            if(response.status == 200){

                                // display flash message
                                $('#alert').html('Pool Created !').css("color","#47a447");

                                // reload pools
                                $scope.getPools();

                                // empty all form fields
                                $scope.pool = {};

                                // fade and remove flash message
                                setTimeout(function(){
                                    $('#alert').html(' &nbsp');
                                    },5000);

                            } else {
                                console.log('could not create pool');
                            }
                        }
                    );
                }
            }
        };

        $scope.copyPool = function(destPoolId, srcPoolId){

            // not allowed in demo mode
            if(!$scope.user.session.demo){

                // prepare ajax data
                var ajaxData = {
                    'profiles_list': {
                        'poolID': destPoolId
                        }
                };

                // get active profiles list by poolID
                ajaxService.fn($scope.user.session.ajaxurl, ajaxData,
                    function (response) {
                        if(response.status == 200){

                            // there are active profiles in destination pool
                            if(response.data.length > 0){

                                // display flash message
                                $('#alert').html('Cannot copy, destination active pool is not empty !').css("color","red");

                                // fade and remove flash message
                                setTimeout(function(){
                                    $('#alert').html(' &nbsp');
                                    },5000);

                            } else {

                                // display flash message
                                $('#alert').html('Copying pool !').css("color","#47a447");

                                // prepare ajax data
                                var ajaxData = {
                                    'copyPool': {
                                        'destPoolId': destPoolId,
                                        'srcPoolId': srcPoolId
                                        }
                                };

                                ajaxService.fn(
                                    $scope.user.session.ajaxurl,
                                    ajaxData,
                                    function (response) {
                                        if(response.status == 200){

                                            // display flash message
                                            $('#alert').html('Pool copied !').css("color","#47a447");

                                            // fade and remove flash message
                                            setTimeout(function(){
                                                $('#alert').html(' &nbsp');
                                                },5000);


                                        } else {
                                            console.log('could not copy pool');
                                        }
                                    }
                                );

                            }

                        } else {

                            // display flash message
                            $('#alert').html('OK lets copy this pool !').css("color","red");

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').html(' &nbsp');
                                },5000);

                        }
                    }
                );
            }
        };

        $scope.getImagesList = function(){

            // prepare ajax data
            var ajaxData = {
                'imagesList': {
                    'all': 1
                    }
            };

            ajaxService.fn(
                $scope.user.session.ajaxurl,
                ajaxData,
                function (response) {
                    if(response.status == 200){

                        $scope.imagesList = response.data;

                    } else {
                        console.log('could not create pool');
                    }
                }
            );
        };

        // update FSK for a pictureID
        $scope.updateFsk = function(id, fsk){

            // prepare ajax data
            var ajaxData = {
                'updateFsk': {
                    'imageID': id,
                    'fsk': fsk
                    }
            };

            ajaxService.fn(
                $scope.user.session.ajaxurl,
                ajaxData,
                function (response) {
                    if(response.status == 200){

                        // update view
                        var found = $scope.imagesList.find(function(element) {
                          return element.imageID == id;
                        });
                        found.fsk = fsk;

                        // display flash message
                        $('#alert').html('Image Updated !').css("color","#47a447");

                        // fade and remove flash message
                        setTimeout(function(){
                            $('#alert').html(' &nbsp');
                            },5000);

                    } else {
                        console.log('could not update picture');
                    }
                }
            );
        };

        $scope.getProfileNewTab = function(profileID){

            var url = $scope.user.session.editProfileUrl;
            var pos = url.indexOf("=");
            var output = url.substr(0, pos+1) + profileID + '&' + url.substr(pos+1);

            // same window
            //window.location.href = output;

            // new window
            window.open(output);
        };

        $scope.copyProfile = function(poolId, profileId, profileName){

            // not allowed in demo mode
            if(!$scope.user.session.demo){

                // check if profile name allready exists in destination pool
                var ajaxData = { // prepare ajax data
                    'profile_is_unique': {
                        'name': profileName.toLowerCase(),
                        'poolID': poolId
                        }
                };
                ajaxService.fn(
                    $scope.user.session.ajaxurl,
                    ajaxData,
                    function (response) {

                        // there is allready a profile with this name in destination pool
                        if(response.status == 200){

                            // display flash message
                            $('#alert').html('Cannot copy, there is allready a profile with this name in destination pool !').css("color","red").css("top","40px");

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').html(' &nbsp');
                                },5000);


                        } else {

                            var url = $scope.user.session.editProfileUrl;

                            var pos = url.indexOf("&");

                            var output = url.substr(0, pos) + profileId + '&copy=' + poolId + url.substr(pos);

                            //window.location.href = output;
                            window.open(output);

                        }
                    }
                );
            }
        };

        $scope.deleteProfile = function(profileId){

            // not allowed in demo mode
            if(!$scope.user.session.demo){

                // prepare ajax data
                var ajaxData = {
                    'deleteProfile': {
                        'profileID': profileId
                        }
                };
                ajaxService.fn(
                    $scope.user.session.ajaxurl,
                    ajaxData,
                    function (response) {

                        if(response.status == 200){

                            // display flash message
                            $('#alert').html('Profile deleted !').css("color","#47a447").css("top","20px");

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').html(' &nbsp');
                                },5000);

                            // update
                            $scope.filterProfiles();


                        } else {

                            // display flash message
                            $('#alert').html('Could not delete Profile !').css("color","red").css("top","20px");

                            // fade and remove flash message
                            setTimeout(function(){
                                $('#alert').html(' &nbsp');
                                },5000);

                        }
                    }
                );
            }
        };

        $scope.getProfilesList = function(id, fsk){

            // prepare ajax data
            var ajaxData = {
                'profiles_list': {
                    'poolID': id
                    }
            };

            ajaxService.fn(
                $scope.user.session.ajaxurl,
                ajaxData,
                function (response) {
                    if(response.status == 200){

                        console.log(response.data);

                        //$scope.profilesList = response.data;

                    } else {
                        console.log('could not load profiles.');
                    }
                }
            );
        };

        $scope.home = function(){
            window.location.href = $scope.user.session.url;
        };

        // scroll to top
        $scope.scroll = function(){
            console.log('ici');
            window.scrollTo(0,0);
          };

        $("#stats-toggle").click(function(e) {
        });
        $("#chat-toggle").click(function(e) {
        });
        $("#events-toggle").click(function(e) {
        });

        // DEPRECATE since use of dropzone.js
        $('.images').change(function(){

            var _URL = window.URL || window.webkitURL;
            var i = 0, files = this.files, file = files[files.length - 1];

            // Max video upload file 20 MB
            if(file.size > 30000000){
                $('#vids').html('Max upload video size 20MB (1)').css('color','red').css('float','right').css('margin-right','110px');
                $('#add_vids').hide();
                $('#save-changes').hide();
            }
            else {
                $('#add_vids').show();
                $('#save-changes').show();
                $('#vids').html('');
            }

            // thumbnail only 160x160
            if (typeof file !== "undefined" ) {

                if(file.name.toLowerCase().indexOf('thumb') != -1){

                    img = new Image();

                    img.onload = function(){

                        document.getElementById('pics').innerHTML = '';

                        if (this.width != 160) {
                            $('#pics').html('Thumbnail must be 160x160').css('color','red').css('margin-right','110px');
                            $('#add_pic').hide();
                            $('#save-changes').hide();
                        }
                        else {
                            $('#add_pic').show();
                            $('#save-changes').show();
                            $('#pics').html('');
                        }
                    };

                    img.src = _URL.createObjectURL(file);

                }
                else {
                    //document.getElementById('pics').innerHTML = '';
                    //$('#add_pic').show();
                    //$('#save-changes').show();
                }
            }
        });

        // DEPRECATE since use of dropzone.js . validate further added files ("add another picture" function)
        $("#img").bind("DOMSubtreeModified", function() {

            //console.log('subtree1');

            $('.images').change(function(){

                var _URL = window.URL || window.webkitURL;

                var i = 0, files = this.files, file = files[files.length - 1];

                // loop the thumbnail checkboxes
                j=0;
                $("input[name='thumb[]']").each(function () {

                    if($(this).is(':checked')){
                        $(".images").each(function(){
                            var i = 0, files = this.files, file = files[files.length - 1];
                            if (typeof file !== "undefined" ) {

                                var fileNameIndex = $('input[name="images[]"]').get(j).value.lastIndexOf("\\") + 1;
                                var filename = $('input[name="images[]"]').get(j).value.substr(fileNameIndex);

                                if(file.name == filename){
                                    console.log(filename = ' is checked');
                                }
                            }
                        })
                        var fileNameIndex = $('input[name="images[]"]').get(j).value.lastIndexOf("\\") + 1;
                        var filename = $('input[name="images[]"]').get(j).value.substr(fileNameIndex);
                        $("input[name='thumb[]']")[j].value = filename;
                    }
                    j++;
                });

                if (typeof file !== "undefined" ) {

                    if(file.name.toLowerCase().indexOf('thumb') != -1){

                        img = new Image();

                        img.onload = function(){

                            $('#pics').html('');

                            if (this.width != 160) {
                                $('#pics').html('Thumbnails must be 160x160!').css('color','red').css('float','right').css('margin-right','110px');

                                $('#add_pic').hide();
                                $('#save-changes').hide();
                            }
                            else {
                                $('#add_pic').show();
                                $('#save-changes').show();
                            }
                        };

                        img.src = _URL.createObjectURL(file);
                    }
                    else {
                        document.getElementById('pics').innerHTML = '';
                        $('#add_pic').show();
                        $('#save-changes').show();
                    }
                }

            });
        });

        // DEPRECATE since use of dropzone.js . Validate thumbnail upload 160x160 pixels
        $("input[name='thumb[]']").change(function(){

            // clear alert message
            if(!$(this).is(':checked')){
                $('#pics').html('');
                $('#add_pic').show();
                $('#save-changes').show();
            }

            if($(this).is(':checked')){
                $(".images").each(function(){
                    var _URL = window.URL || window.webkitURL;
                    var i = 0, files = this.files, file = files[files.length - 1];
                    if (typeof file !== "undefined" ) {

                        img = new Image();

                        img.onload = function(){

                            $('#pics').html('');

                            if (this.width != 160) {
                                $('#pics').html('Thumbnails must be 160x160!').css('color','red').css('float','right').css('margin-right','110px');

                                $('#add_pic').hide();
                                $('#save-changes').hide();
                            }
                            else {
                                $('#add_pic').show();
                                $('#save-changes').show();
                            }
                        };

                        img.src = _URL.createObjectURL(file);

                    }
                });
            }
        });
    }]); // End appCtrl

    app.controller('eventToggleCtrl', ['$scope', '$http',function($scope, $http){

        $scope.validateFormEvents = function(e){

            $('#projectError').html('');
            $('#eventError').html('');

            // empty form submit
            if(!$("#project").val() && !$("#event").val()){
                $('#projectError').html(' Please enter a project and/or an event name');
                $('#projectError').css('color', 'red');
                e.preventDefault();
            }

            // form validation
            else if( ($("#project").val() && !$("#project").val().match(/^[a-zA-Z0-9_-\s]{3,30}$/))
                || ($("#event").val() && !$("#event").val().match(/^[a-zA-Z0-9_-\s]{3,30}$/)) ){

                $('#projectError').html(' only (A-Z,a-z,0-9), underscore (_) or hyphen (-) (min 3 and max 20 char)');
                $('#projectError').css('color', 'red');
                e.preventDefault();
            }

            // at least one param is valid
            else{

                $scope.project = $("#project").val();
                $scope.evnt = $("#event").val();

                // text "Loading..." during get data
                $scope.loader = { loading: true };

                $http({
                    method: 'POST',
                    url: $scope.user.session.ajaxurl,
                    data: { project: $("#project").val(), 'event': $('#event').val() }
                }).then(function successCallback(response) {
                    (function() {
                        var arr = [];
                        for (var prop in response.data) {
                            arr.push(response.data[prop]);
                        }
                        $scope.events = arr;
                        $scope.events.project = $scope.events[$scope.events.length-2];
                        $scope.events.event = $scope.events[$scope.events.length-1];
                        $scope.loader.loading = false;
                    })()

                }, function errorCallback(response) {
                    // called asynchronously if an error occurs or server returns response with an error status.
                    $('#projectError').html(' Project or Event not found.');
                    $('#projectError').css('color', 'red');
                    $scope.loader.loading = false;
                }); // end $http

                e.preventDefault();

            } // end else
        }; // end validateFormEvents

        // select events by creatime
        $scope.dateCompare = function(prop, search) {
            if(search){
                return function(ev){
                    if (Date.parse(ev[prop]) > Date.parse(search))
                        return true;
                    else {
                        return false;
                    }
                }
            }
        };
    }]);// End eventToggleCtrl

    app.controller('frameCtrl', ['$scope', '$window', '$http', 'ajaxService', function($scope, $window, $http, ajaxService){

        // function: get profile
        $scope.getProfile = function (profileID) {

            ajaxService.fn(
                $scope.user.session.ajaxurl,    // URL
                { 'profileID': profileID },               // Parameters
                function (response) {           // Callback function
                    if(response.status == 200){
                        $scope.profile = response.data;

                        // add default image
                        if($scope.profile.images.length == 0){
                            $scope.profile.images.push($scope.user.session.defaultPictureUrl);
                        }



                        if($scope.profile.poolID == 0) $scope.profile.poolID = 1;
                    } else {
                        console.log('could not load profile');
                    }
                }
            );
        };

        $scope.getProfile($scope.user.session.profileID);


        // get profile images page
        $scope.getImages = function(usID, profileID, view, scheme){


            var landingUrl = "http://" + $window.location.host + "/get_images/" + usID + '/' + profileID + '/' + view + '/' + scheme;

            return landingUrl;

            // option 1: open in same tab
            //$window.location.href = landingUrl;

            // option 2: open in a new tab
            $window.open(landingUrl, '_blank');

        }

        // chattool functions für frameCtrl
        $scope.chattool = {
            "open_iframe"   : function(usID, profileID, view, scheme){
                // only for fake profiles
                if(angular.isUndefined($scope.profile.isuser)){
                    if($scope.profile.images_mod.length > 0){
                        console.log("http://" + $window.location.host + "/get_images/" + usID + '/' + profileID + '/' + view + '/' + scheme);
                        parent.postMessage({
                            "do"    : "open_bragiprofile_media",
                            "url"   : "http://" + $window.location.host + "/get_images/" + usID + '/' + profileID + '/' + view + '/' + scheme
                            }, "*");
                        }
                    }
                },
            "close_iframe"  : function(){
                parent.postMessage({
                    "do"    :"close_bragiprofile_media"
                    }, "*");
                }
            };
    }]); // End frameCtrl

    app.controller('pageCtrl', ['$scope', '$window', '$http', 'ajaxService', function($scope, $window, $http, ajaxService){

        // function: get profile
        $scope.getProfile = function (profileID) {

            ajaxService.fn(
                $scope.user.session.ajaxurl,    // URL
                { 'profileID': profileID },               // Parameters
                function (response) {           // Callback function
                    if(response.status == 200){
                        $scope.profile = response.data;
                        if($scope.profile.poolID == 0) $scope.profile.poolID = 1;
                    } else {
                        console.log('could not load profile');
                    }
                }
            );
        };

        $scope.getProfile($scope.user.session.profileID);

        // copy to clipboard
        $scope.copyToClipboard = function(i, name){

            var urlField = document.querySelector('#url-field'+name+i);
            // select the contents
            urlField.select();

            document.execCommand('copy'); // or 'cut'
        };

        // Add style to clicked element
        $scope.isClicked = function(id){

            $("#"+id).text('Der Link steht in Zwischenablage!');

            // 1. need to use attr() instead of css() because of "!important"
            // 2. normal behaviour of attr('style') is to delete any previous css style,
            //    so all style attributes should be defined at ones in a string
            $("#"+id).attr('style', 'color:black !important; background-color:#428bca !important;');

            /*
                In case the previous code does not work (IE6 or other browsers),
                or it is needed to add more attrs, i.e. attr('style', 'string1', 'class', 'string2'):

                This map() function should transform an object like this:

                {
                    'color': 'black !important',
                    'background-color': '#428bca !important'
                }

                In a string like that:

                'color:black !important;background-color:#428bca !important;'
            */
            function map(map) {
                var cssValue = [];

                for (var o in map) {
                    cssValue.push(o + ':' + map[o] + ';')
                }

                // array to string
                return cssValue.join('');
            }

            // change style after 4 sec
            setTimeout(
                function(){
                    $("#"+id).text('Kopiere Link in Zwischenablage');
                    //$("#"+id).attr('style', 'color: green !important');
                    $("#"+id).attr('style', 'background-color: black !important');
                },
                4000
            );
        };

        // chattool functions für pageCtrl
        $scope.chattool = {
            "close_iframe"  : function(){
                parent.postMessage({
                    "do"    :"close_bragiprofile_media"
                    }, "*");
                },
            "copy_media_url": function(url){
                console.log(url);
                parent.postMessage({
                    "do"    : "copy_media_url",
                    "url"   : url
                    }, "*");
                }
            };

        // animation when click on profile picture
        $scope.picClicked = function(id){

            $("#"+id).attr('style', 'width:210px !important; opacity:0.5 !important;');

            /*
            var filterVal = 'grayscale(100%)';
            $("#"+id)
              .css('filter',filterVal)
              .css('webkitFilter',filterVal)
              .css('mozFilter',filterVal)
              .css('oFilter',filterVal)
              .css('msFilter',filterVal);
              */

            //$("#"+id).text('Der Link steht in Zwischenablage!');

            // 1. need to use attr() instead of css() because of "!important"
            // 2. normal behaviour of attr('style') is to delete any previous css style,
            //    so all style attributes should be defined at ones in a string
            //$("#"+id).attr('style', 'color:black !important; width:210px !important;');

            /*
                In case the previous code does not work (IE6 or other browsers),
                or it is needed to add more attrs, i.e. attr('style', 'string1', 'class', 'string2'):

                This map() function should transform an object like this:

                {
                    'color': 'black !important',
                    'background-color': '#428bca !important'
                }

                In a string like that:

                'color:black !important;background-color:#428bca !important;'
            */
            function map(map) {
                var cssValue = [];

                for (var o in map) {
                    cssValue.push(o + ':' + map[o] + ';')
                }

                // array to string
                return cssValue.join('');
            }

            // change style after 4 sec
            setTimeout(
                function(){
                    $("#"+id).text('Kopiere Link in Zwischenablage');
                    //$("#"+id).attr('style', 'color: green !important');
                    $("#"+id).attr('style', 'background-color: black !important');
                },
                200
            );

        };
    }]); // End pageCtrl


    // add additional button 'upload_a_file' (additional picture in edit_profile view)
    $('#add_btn').click(function(){

        var $clone = $('#hidden_btn').clone(true, true).attr('id','').removeClass('hidden');
        $('#hidden_btn').before($clone);
        var $clone = $('#hidden_checkbox').clone(true, true).attr('id','').removeClass('hidden');
        $('#hidden_btn').before($clone);
        var $clone = $('#hidden_label').clone(true, true).attr('id','').removeClass('hidden');
        $('#hidden_btn').before($clone);
        var $clone = $('#hidden_thumb_checkbox').clone(true, true).attr('id','').removeClass('hidden');
        $('#hidden_btn').before($clone);
        var $clone = $('#hidden_thumb_label').clone(true, true).attr('id','').removeClass('hidden');
        $('#hidden_btn').before($clone);
    });

    // add additional button 'upload_a_file' (additional video in edit_profile view)
    $('#add_vid_btn').click(function(){
        var $clone = $('#vid_hidden_btn').clone().attr('id','').removeClass('hidden');
        $('#vid_hidden_btn').before($clone);
    });

    // dropdown submenu
    $('.dropdown-submenu a.test').on("click", function(e){
        $(this).next('ul').toggle();
        e.stopPropagation();
        e.preventDefault();
      });

    // Activate Tooltipster
    $(document).ready(function() {
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

        var tooltipInstance;
         $("body").on('mouseover', '.demo:not(.tooltipstered)', function(){
             tooltipInstance = $(this).tooltipster({
                 contentCloning: true,
                 contentAsHTML : true,
                 side : "top"
             });
             tooltipInstance.tooltipster('open');
         });
    });

    // fade and remove flash message div
    setTimeout(function(){
        $('#flash').fadeOut(2000,function(){$(this).remove();});
        },5000);

    function longestCommonSubstring(str1, str2){
        if (!str1 || !str2)
            return {
                length: 0,
                sequence: "",
                offset: 0
            };

        var sequence = "",
            str1Length = str1.length,
            str2Length = str2.length,
            num = new Array(str1Length),
            maxlen = 0,
            lastSubsBegin = 0;

        for (var i = 0; i < str1Length; i++) {
            var subArray = new Array(str2Length);
            for (var j = 0; j < str2Length; j++)
                subArray[j] = 0;
            num[i] = subArray;
        }
        var thisSubsBegin = null;
        for (var i = 0; i < str1Length; i++)
        {
            for (var j = 0; j < str2Length; j++)
            {
                if (str1[i] !== str2[j])
                    num[i][j] = 0;
                else
                {
                    if ((i === 0) || (j === 0))
                        num[i][j] = 1;
                    else
                        num[i][j] = 1 + num[i - 1][j - 1];

                    if (num[i][j] > maxlen)
                    {
                        maxlen = num[i][j];
                        thisSubsBegin = i - num[i][j] + 1;
                        if (lastSubsBegin === thisSubsBegin)
                        {//if the current LCS is the same as the last time this block ran
                            sequence += str1[i];
                        }
                        else //this block resets the string builder if a different LCS is found
                        {
                            lastSubsBegin = thisSubsBegin;
                            sequence= ""; //clear it
                            sequence += str1.substr(lastSubsBegin, (i + 1) - lastSubsBegin);
                        }
                    }
                }
            }
        }
        return {
            length: maxlen,
            sequence: sequence,
            offset: thisSubsBegin
        };
    }


})(jQuery);


/* ********************************************* angular-masonry ****************************************************** */

(function () {
  'use strict';
  angular.module('wu.masonry', []).controller('MasonryCtrl', [
    '$scope',
    '$element',
    '$timeout',
    function controller($scope, $element, $timeout) {
      var bricks = {};
      var schedule = [];
      var destroyed = false;
      var self = this;
      var timeout = null;
      this.preserveOrder = false;
      this.scheduleMasonryOnce = function scheduleMasonryOnce() {
        var args = arguments;
        var found = schedule.filter(function filterFn(item) {
            return item[0] === args[0];
          }).length > 0;
        if (!found) {
          this.scheduleMasonry.apply(null, arguments);
        }
      };
      this.scheduleMasonry = function scheduleMasonry() {
        if (timeout) {
          $timeout.cancel(timeout);
        }
        schedule.push([].slice.call(arguments));
        timeout = $timeout(function runMasonry() {
          if (destroyed) {
            return;
          }
          schedule.forEach(function scheduleForEach(args) {
            $element.masonry.apply($element, args);
          });
          schedule = [];
        }, 30);
      };
      function defaultLoaded($element) {
        $element.addClass('loaded');
      }
      this.appendBrick = function appendBrick(element, id) {
        if (destroyed) {
          return;
        }
        function _append() {
          if (Object.keys(bricks).length === 0) {
            $element.masonry('resize');
          }
          if (bricks[id] === undefined) {
            bricks[id] = true;
            defaultLoaded(element);
            $element.masonry('appended', element, true);
          }
        }
        function _layout() {
          self.scheduleMasonryOnce('layout');
        }
        if (self.preserveOrder) {
          _append();
          element.imagesLoaded(_layout);
        } else {
          element.imagesLoaded(function imagesLoaded() {
            _append();
            _layout();
          });
        }
      };
      this.removeBrick = function removeBrick(id, element) {
        if (destroyed) {
          return;
        }
        delete bricks[id];
        $element.masonry('remove', element);
        this.scheduleMasonryOnce('layout');
      };
      this.destroy = function destroy() {
        destroyed = true;
        if ($element.data('masonry')) {
          $element.masonry('destroy');
        }
        $scope.$emit('masonry.destroyed');
        bricks = [];
      };
      this.reload = function reload() {
        $element.masonry();
        $scope.$emit('masonry.reloaded');
      };
    }
  ]).directive('masonry', function masonryDirective() {
    return {
      restrict: 'AE',
      controller: 'MasonryCtrl',
      link: {
        pre: function preLink(scope, element, attrs, ctrl) {
          var attrOptions = scope.$eval(attrs.masonry || attrs.masonryOptions);
          var options = angular.extend({
              itemSelector: attrs.itemSelector || '.masonry-brick',
              columnWidth: parseInt(attrs.columnWidth, 10)
            }, attrOptions || {});
            console.log(options)
          element.masonry(options);
          var preserveOrder = scope.$eval(attrs.preserveOrder);
          ctrl.preserveOrder = preserveOrder !== false && attrs.preserveOrder !== undefined;
          scope.$emit('masonry.created', element);
          scope.$on('$destroy', ctrl.destroy);
        }
      }
    };
  }).directive('masonryBrick', function masonryBrickDirective() {
    return {
      restrict: 'AC',
      require: '^masonry',
      scope: true,
      link: {
        pre: function preLink(scope, element, attrs, ctrl) {
          var id = scope.$id, index;
          ctrl.appendBrick(element, id);
          element.on('$destroy', function () {
            ctrl.removeBrick(id, element);
          });
          scope.$on('masonry.reload', function () {
            ctrl.reload();
          });
          scope.$watch('$index', function () {
            if (index !== undefined && index !== scope.$index) {
              ctrl.scheduleMasonryOnce('reloadItems');
              ctrl.scheduleMasonryOnce('layout');
            }
            index = scope.$index;
          });
        }
      }
    };
  });
}());

/* ********************************************* end of angular-masonry ************************************************/


/* ui-select
 *
 * http://github.com/angular-ui/ui-select
 * Version: 0.19.8 - 2017-04-18T05:43:43.673Z
 * License: MIT
 ***********************************************************************************************************************/

(function () {
"use strict";
var KEY = {
    TAB: 9,
    ENTER: 13,
    ESC: 27,
    SPACE: 32,
    LEFT: 37,
    UP: 38,
    RIGHT: 39,
    DOWN: 40,
    SHIFT: 16,
    CTRL: 17,
    ALT: 18,
    PAGE_UP: 33,
    PAGE_DOWN: 34,
    HOME: 36,
    END: 35,
    BACKSPACE: 8,
    DELETE: 46,
    COMMAND: 91,

    MAP: { 91 : "COMMAND", 8 : "BACKSPACE" , 9 : "TAB" , 13 : "ENTER" , 16 : "SHIFT" , 17 : "CTRL" , 18 : "ALT" , 19 : "PAUSEBREAK" , 20 : "CAPSLOCK" , 27 : "ESC" , 32 : "SPACE" , 33 : "PAGE_UP", 34 : "PAGE_DOWN" , 35 : "END" , 36 : "HOME" , 37 : "LEFT" , 38 : "UP" , 39 : "RIGHT" , 40 : "DOWN" , 43 : "+" , 44 : "PRINTSCREEN" , 45 : "INSERT" , 46 : "DELETE", 48 : "0" , 49 : "1" , 50 : "2" , 51 : "3" , 52 : "4" , 53 : "5" , 54 : "6" , 55 : "7" , 56 : "8" , 57 : "9" , 59 : ";", 61 : "=" , 65 : "A" , 66 : "B" , 67 : "C" , 68 : "D" , 69 : "E" , 70 : "F" , 71 : "G" , 72 : "H" , 73 : "I" , 74 : "J" , 75 : "K" , 76 : "L", 77 : "M" , 78 : "N" , 79 : "O" , 80 : "P" , 81 : "Q" , 82 : "R" , 83 : "S" , 84 : "T" , 85 : "U" , 86 : "V" , 87 : "W" , 88 : "X" , 89 : "Y" , 90 : "Z", 96 : "0" , 97 : "1" , 98 : "2" , 99 : "3" , 100 : "4" , 101 : "5" , 102 : "6" , 103 : "7" , 104 : "8" , 105 : "9", 106 : "*" , 107 : "+" , 109 : "-" , 110 : "." , 111 : "/", 112 : "F1" , 113 : "F2" , 114 : "F3" , 115 : "F4" , 116 : "F5" , 117 : "F6" , 118 : "F7" , 119 : "F8" , 120 : "F9" , 121 : "F10" , 122 : "F11" , 123 : "F12", 144 : "NUMLOCK" , 145 : "SCROLLLOCK" , 186 : ";" , 187 : "=" , 188 : "," , 189 : "-" , 190 : "." , 191 : "/" , 192 : "`" , 219 : "[" , 220 : "\\" , 221 : "]" , 222 : "'"
    },

    isControl: function (e) {
        var k = e.which;
        switch (k) {
        case KEY.COMMAND:
        case KEY.SHIFT:
        case KEY.CTRL:
        case KEY.ALT:
            return true;
        }

        if (e.metaKey || e.ctrlKey || e.altKey) return true;

        return false;
    },
    isFunctionKey: function (k) {
        k = k.which ? k.which : k;
        return k >= 112 && k <= 123;
    },
    isVerticalMovement: function (k){
      return ~[KEY.UP, KEY.DOWN].indexOf(k);
    },
    isHorizontalMovement: function (k){
      return ~[KEY.LEFT,KEY.RIGHT,KEY.BACKSPACE,KEY.DELETE].indexOf(k);
    },
    toSeparator: function (k) {
      var sep = {ENTER:"\n",TAB:"\t",SPACE:" "}[k];
      if (sep) return sep;
      // return undefined for special keys other than enter, tab or space.
      // no way to use them to cut strings.
      return KEY[k] ? undefined : k;
    }
  };

function isNil(value) {
  return angular.isUndefined(value) || value === null;
}

/**
 * Add querySelectorAll() to jqLite.
 *
 * jqLite find() is limited to lookups by tag name.
 * TODO This will change with future versions of AngularJS, to be removed when this happens
 *
 * See jqLite.find - why not use querySelectorAll? https://github.com/angular/angular.js/issues/3586
 * See feat(jqLite): use querySelectorAll instead of getElementsByTagName in jqLite.find https://github.com/angular/angular.js/pull/3598
 */
if (angular.element.prototype.querySelectorAll === undefined) {
  angular.element.prototype.querySelectorAll = function(selector) {
    return angular.element(this[0].querySelectorAll(selector));
  };
}

/**
 * Add closest() to jqLite.
 */
if (angular.element.prototype.closest === undefined) {
  angular.element.prototype.closest = function( selector) {
    var elem = this[0];
    var matchesSelector = elem.matches || elem.webkitMatchesSelector || elem.mozMatchesSelector || elem.msMatchesSelector;

    while (elem) {
      if (matchesSelector.bind(elem)(selector)) {
        return elem;
      } else {
        elem = elem.parentElement;
      }
    }
    return false;
  };
}

var latestId = 0;

var uis = angular.module('ui.select', [])

.constant('uiSelectConfig', {
  theme: 'bootstrap',
  searchEnabled: true,
  sortable: false,
  placeholder: '', // Empty by default, like HTML tag <select>
  refreshDelay: 1000, // In milliseconds
  closeOnSelect: true,
  skipFocusser: false,
  dropdownPosition: 'auto',
  removeSelected: true,
  resetSearchInput: true,
  generateId: function() {
    return latestId++;
  },
  appendToBody: false,
  spinnerEnabled: false,
  spinnerClass: 'glyphicon glyphicon-refresh ui-select-spin',
  backspaceReset: true
})

// See Rename minErr and make it accessible from outside https://github.com/angular/angular.js/issues/6913
.service('uiSelectMinErr', function() {
  var minErr = angular.$$minErr('ui.select');
  return function() {
    var error = minErr.apply(this, arguments);
    var message = error.message.replace(new RegExp('\nhttp://errors.angularjs.org/.*'), '');
    return new Error(message);
  };
})

// Recreates old behavior of ng-transclude. Used internally.
.directive('uisTranscludeAppend', function () {
  return {
    link: function (scope, element, attrs, ctrl, transclude) {
        transclude(scope, function (clone) {
          element.append(clone);
        });
      }
    };
})

/**
 * Highlights text that matches $select.search.
 *
 * Taken from AngularUI Bootstrap Typeahead
 * See https://github.com/angular-ui/bootstrap/blob/0.10.0/src/typeahead/typeahead.js#L340
 */
.filter('highlight', function() {
  function escapeRegexp(queryToEscape) {
    return ('' + queryToEscape).replace(/([.?*+^$[\]\\(){}|-])/g, '\\$1');
  }

  return function(matchItem, query) {
    return query && matchItem ? ('' + matchItem).replace(new RegExp(escapeRegexp(query), 'gi'), '<span class="ui-select-highlight">$&</span>') : matchItem;
  };
})

/**
 * A read-only equivalent of jQuery's offset function: http://api.jquery.com/offset/
 *
 * Taken from AngularUI Bootstrap Position:
 * See https://github.com/angular-ui/bootstrap/blob/master/src/position/position.js#L70
 */
.factory('uisOffset',
  ['$document', '$window',
  function ($document, $window) {

  return function(element) {
    var boundingClientRect = element[0].getBoundingClientRect();
    return {
      width: boundingClientRect.width || element.prop('offsetWidth'),
      height: boundingClientRect.height || element.prop('offsetHeight'),
      top: boundingClientRect.top + ($window.pageYOffset || $document[0].documentElement.scrollTop),
      left: boundingClientRect.left + ($window.pageXOffset || $document[0].documentElement.scrollLeft)
    };
  };
}]);

uis.directive('uiSelectChoices',
  ['uiSelectConfig', 'uisRepeatParser', 'uiSelectMinErr', '$compile', '$window',
  function(uiSelectConfig, RepeatParser, uiSelectMinErr, $compile, $window) {

  return {
    restrict: 'EA',
    require: '^uiSelect',
    replace: true,
    transclude: true,
    templateUrl: function(tElement) {
      // Needed so the uiSelect can detect the transcluded content
      tElement.addClass('ui-select-choices');

      // Gets theme attribute from parent (ui-select)
      var theme = tElement.parent().attr('theme') || uiSelectConfig.theme;
      return theme + '/choices.tpl.html';
    },

    compile: function(tElement, tAttrs) {

      if (!tAttrs.repeat) throw uiSelectMinErr('repeat', "Expected 'repeat' expression.");

      // var repeat = RepeatParser.parse(attrs.repeat);
      var groupByExp = tAttrs.groupBy;
      var groupFilterExp = tAttrs.groupFilter;

      if (groupByExp) {
        var groups = tElement.querySelectorAll('.ui-select-choices-group');
        if (groups.length !== 1) throw uiSelectMinErr('rows', "Expected 1 .ui-select-choices-group but got '{0}'.", groups.length);
        groups.attr('ng-repeat', RepeatParser.getGroupNgRepeatExpression());
      }

      var parserResult = RepeatParser.parse(tAttrs.repeat);

      var choices = tElement.querySelectorAll('.ui-select-choices-row');
      if (choices.length !== 1) {
        throw uiSelectMinErr('rows', "Expected 1 .ui-select-choices-row but got '{0}'.", choices.length);
      }

      choices.attr('ng-repeat', parserResult.repeatExpression(groupByExp))
             .attr('ng-if', '$select.open'); //Prevent unnecessary watches when dropdown is closed


      var rowsInner = tElement.querySelectorAll('.ui-select-choices-row-inner');
      if (rowsInner.length !== 1) {
        throw uiSelectMinErr('rows', "Expected 1 .ui-select-choices-row-inner but got '{0}'.", rowsInner.length);
      }
      rowsInner.attr('uis-transclude-append', ''); //Adding uisTranscludeAppend directive to row element after choices element has ngRepeat

      // If IE8 then need to target rowsInner to apply the ng-click attr as choices will not capture the event.
      var clickTarget = $window.document.addEventListener ? choices : rowsInner;
      clickTarget.attr('ng-click', '$select.select(' + parserResult.itemName + ',$select.skipFocusser,$event)');

      return function link(scope, element, attrs, $select) {


        $select.parseRepeatAttr(attrs.repeat, groupByExp, groupFilterExp); //Result ready at $select.parserResult
        $select.disableChoiceExpression = attrs.uiDisableChoice;
        $select.onHighlightCallback = attrs.onHighlight;
        $select.minimumInputLength = parseInt(attrs.minimumInputLength) || 0;
        $select.dropdownPosition = attrs.position ? attrs.position.toLowerCase() : uiSelectConfig.dropdownPosition;

        scope.$watch('$select.search', function(newValue) {
          if(newValue && !$select.open && $select.multiple) $select.activate(false, true);
          $select.activeIndex = $select.tagging.isActivated ? -1 : 0;
          if (!attrs.minimumInputLength || $select.search.length >= attrs.minimumInputLength) {
            $select.refresh(attrs.refresh);
          } else {
            $select.items = [];
          }
        });

        attrs.$observe('refreshDelay', function() {
          // $eval() is needed otherwise we get a string instead of a number
          var refreshDelay = scope.$eval(attrs.refreshDelay);
          $select.refreshDelay = refreshDelay !== undefined ? refreshDelay : uiSelectConfig.refreshDelay;
        });

        scope.$watch('$select.open', function(open) {
          if (open) {
            tElement.attr('role', 'listbox');
            $select.refresh(attrs.refresh);
          } else {
            element.removeAttr('role');
          }
        });
      };
    }
  };
}]);

/**
 * Contains ui-select "intelligence".
 *
 * The goal is to limit dependency on the DOM whenever possible and
 * put as much logic in the controller (instead of the link functions) as possible so it can be easily tested.
 */
uis.controller('uiSelectCtrl',
  ['$scope', '$element', '$timeout', '$filter', '$$uisDebounce', 'uisRepeatParser', 'uiSelectMinErr', 'uiSelectConfig', '$parse', '$injector', '$window',
  function($scope, $element, $timeout, $filter, $$uisDebounce, RepeatParser, uiSelectMinErr, uiSelectConfig, $parse, $injector, $window) {

  var ctrl = this;

  var EMPTY_SEARCH = '';

  ctrl.placeholder = uiSelectConfig.placeholder;
  ctrl.searchEnabled = uiSelectConfig.searchEnabled;
  ctrl.sortable = uiSelectConfig.sortable;
  ctrl.refreshDelay = uiSelectConfig.refreshDelay;
  ctrl.paste = uiSelectConfig.paste;
  ctrl.resetSearchInput = uiSelectConfig.resetSearchInput;
  ctrl.refreshing = false;
  ctrl.spinnerEnabled = uiSelectConfig.spinnerEnabled;
  ctrl.spinnerClass = uiSelectConfig.spinnerClass;
  ctrl.removeSelected = uiSelectConfig.removeSelected; //If selected item(s) should be removed from dropdown list
  ctrl.closeOnSelect = true; //Initialized inside uiSelect directive link function
  ctrl.skipFocusser = false; //Set to true to avoid returning focus to ctrl when item is selected
  ctrl.search = EMPTY_SEARCH;

  ctrl.activeIndex = 0; //Dropdown of choices
  ctrl.items = []; //All available choices

  ctrl.open = false;
  ctrl.focus = false;
  ctrl.disabled = false;
  ctrl.selected = undefined;

  ctrl.dropdownPosition = 'auto';

  ctrl.focusser = undefined; //Reference to input element used to handle focus events
  ctrl.multiple = undefined; // Initialized inside uiSelect directive link function
  ctrl.disableChoiceExpression = undefined; // Initialized inside uiSelectChoices directive link function
  ctrl.tagging = {isActivated: false, fct: undefined};
  ctrl.taggingTokens = {isActivated: false, tokens: undefined};
  ctrl.lockChoiceExpression = undefined; // Initialized inside uiSelectMatch directive link function
  ctrl.clickTriggeredSelect = false;
  ctrl.$filter = $filter;
  ctrl.$element = $element;

  // Use $injector to check for $animate and store a reference to it
  ctrl.$animate = (function () {
    try {
      return $injector.get('$animate');
    } catch (err) {
      // $animate does not exist
      return null;
    }
  })();

  ctrl.searchInput = $element.querySelectorAll('input.ui-select-search');
  if (ctrl.searchInput.length !== 1) {
    throw uiSelectMinErr('searchInput', "Expected 1 input.ui-select-search but got '{0}'.", ctrl.searchInput.length);
  }

  ctrl.isEmpty = function() {
    return isNil(ctrl.selected) || ctrl.selected === '' || (ctrl.multiple && ctrl.selected.length === 0);
  };

  function _findIndex(collection, predicate, thisArg){
    if (collection.findIndex){
      return collection.findIndex(predicate, thisArg);
    } else {
      var list = Object(collection);
      var length = list.length >>> 0;
      var value;

      for (var i = 0; i < length; i++) {
        value = list[i];
        if (predicate.call(thisArg, value, i, list)) {
          return i;
        }
      }
      return -1;
    }
  }

  // Most of the time the user does not want to empty the search input when in typeahead mode
  function _resetSearchInput() {
    if (ctrl.resetSearchInput) {
      ctrl.search = EMPTY_SEARCH;
      //reset activeIndex
      if (ctrl.selected && ctrl.items.length && !ctrl.multiple) {
        ctrl.activeIndex = _findIndex(ctrl.items, function(item){
          return angular.equals(this, item);
        }, ctrl.selected);
      }
    }
  }

    function _groupsFilter(groups, groupNames) {
      var i, j, result = [];
      for(i = 0; i < groupNames.length ;i++){
        for(j = 0; j < groups.length ;j++){
          if(groups[j].name == [groupNames[i]]){
            result.push(groups[j]);
          }
        }
      }
      return result;
    }

  // When the user clicks on ui-select, displays the dropdown list
  ctrl.activate = function(initSearchValue, avoidReset) {
    if (!ctrl.disabled  && !ctrl.open) {
      if(!avoidReset) _resetSearchInput();

      $scope.$broadcast('uis:activate');
      ctrl.open = true;
      ctrl.activeIndex = ctrl.activeIndex >= ctrl.items.length ? 0 : ctrl.activeIndex;
      // ensure that the index is set to zero for tagging variants
      // that where first option is auto-selected
      if ( ctrl.activeIndex === -1 && ctrl.taggingLabel !== false ) {
        ctrl.activeIndex = 0;
      }

      var container = $element.querySelectorAll('.ui-select-choices-content');
      var searchInput = $element.querySelectorAll('.ui-select-search');
      if (ctrl.$animate && ctrl.$animate.on && ctrl.$animate.enabled(container[0])) {
        var animateHandler = function(elem, phase) {
          if (phase === 'start' && ctrl.items.length === 0) {
            // Only focus input after the animation has finished
            ctrl.$animate.off('removeClass', searchInput[0], animateHandler);
            $timeout(function () {
              ctrl.focusSearchInput(initSearchValue);
            });
          } else if (phase === 'close') {
            // Only focus input after the animation has finished
            ctrl.$animate.off('enter', container[0], animateHandler);
            $timeout(function () {
              ctrl.focusSearchInput(initSearchValue);
            });
          }
        };

        if (ctrl.items.length > 0) {
          ctrl.$animate.on('enter', container[0], animateHandler);
        } else {
          ctrl.$animate.on('removeClass', searchInput[0], animateHandler);
        }
      } else {
        $timeout(function () {
          ctrl.focusSearchInput(initSearchValue);
          if(!ctrl.tagging.isActivated && ctrl.items.length > 1) {
            _ensureHighlightVisible();
          }
        });
      }
    }
    else if (ctrl.open && !ctrl.searchEnabled) {
      // Close the selection if we don't have search enabled, and we click on the select again
      ctrl.close();
    }
  };

  ctrl.focusSearchInput = function (initSearchValue) {
    ctrl.search = initSearchValue || ctrl.search;
    ctrl.searchInput[0].focus();
  };

  ctrl.findGroupByName = function(name) {
    return ctrl.groups && ctrl.groups.filter(function(group) {
      return group.name === name;
    })[0];
  };

  ctrl.parseRepeatAttr = function(repeatAttr, groupByExp, groupFilterExp) {
    function updateGroups(items) {
      var groupFn = $scope.$eval(groupByExp);
      ctrl.groups = [];
      angular.forEach(items, function(item) {
        var groupName = angular.isFunction(groupFn) ? groupFn(item) : item[groupFn];
        var group = ctrl.findGroupByName(groupName);
        if(group) {
          group.items.push(item);
        }
        else {
          ctrl.groups.push({name: groupName, items: [item]});
        }
      });
      if(groupFilterExp){
        var groupFilterFn = $scope.$eval(groupFilterExp);
        if( angular.isFunction(groupFilterFn)){
          ctrl.groups = groupFilterFn(ctrl.groups);
        } else if(angular.isArray(groupFilterFn)){
          ctrl.groups = _groupsFilter(ctrl.groups, groupFilterFn);
        }
      }
      ctrl.items = [];
      ctrl.groups.forEach(function(group) {
        ctrl.items = ctrl.items.concat(group.items);
      });
    }

    function setPlainItems(items) {
      ctrl.items = items || [];
    }

    ctrl.setItemsFn = groupByExp ? updateGroups : setPlainItems;

    ctrl.parserResult = RepeatParser.parse(repeatAttr);

    ctrl.isGrouped = !!groupByExp;
    ctrl.itemProperty = ctrl.parserResult.itemName;

    //If collection is an Object, convert it to Array

    var originalSource = ctrl.parserResult.source;

    //When an object is used as source, we better create an array and use it as 'source'
    var createArrayFromObject = function(){
      var origSrc = originalSource($scope);
      $scope.$uisSource = Object.keys(origSrc).map(function(v){
        var result = {};
        result[ctrl.parserResult.keyName] = v;
        result.value = origSrc[v];
        return result;
      });
    };

    if (ctrl.parserResult.keyName){ // Check for (key,value) syntax
      createArrayFromObject();
      ctrl.parserResult.source = $parse('$uisSource' + ctrl.parserResult.filters);
      $scope.$watch(originalSource, function(newVal, oldVal){
        if (newVal !== oldVal) createArrayFromObject();
      }, true);
    }

    ctrl.refreshItems = function (data){
      data = data || ctrl.parserResult.source($scope);
      var selectedItems = ctrl.selected;
      //TODO should implement for single mode removeSelected
      if (ctrl.isEmpty() || (angular.isArray(selectedItems) && !selectedItems.length) || !ctrl.multiple || !ctrl.removeSelected) {
        ctrl.setItemsFn(data);
      }else{
        if ( data !== undefined && data !== null ) {
          var filteredItems = data.filter(function(i) {
            return angular.isArray(selectedItems) ? selectedItems.every(function(selectedItem) {
              return !angular.equals(i, selectedItem);
            }) : !angular.equals(i, selectedItems);
          });
          ctrl.setItemsFn(filteredItems);
        }
      }
      if (ctrl.dropdownPosition === 'auto' || ctrl.dropdownPosition === 'up'){
        $scope.calculateDropdownPos();
      }
      $scope.$broadcast('uis:refresh');
    };

    // See https://github.com/angular/angular.js/blob/v1.2.15/src/ng/directive/ngRepeat.js#L259
    $scope.$watchCollection(ctrl.parserResult.source, function(items) {
      if (items === undefined || items === null) {
        // If the user specifies undefined or null => reset the collection
        // Special case: items can be undefined if the user did not initialized the collection on the scope
        // i.e $scope.addresses = [] is missing
        ctrl.items = [];
      } else {
        if (!angular.isArray(items)) {
          throw uiSelectMinErr('items', "Expected an array but got '{0}'.", items);
        } else {
          //Remove already selected items (ex: while searching)
          //TODO Should add a test
          ctrl.refreshItems(items);

          //update the view value with fresh data from items, if there is a valid model value
          if(angular.isDefined(ctrl.ngModel.$modelValue)) {
            ctrl.ngModel.$modelValue = null; //Force scope model value and ngModel value to be out of sync to re-run formatters
          }
        }
      }
    });

  };

  var _refreshDelayPromise;

  /**
   * Typeahead mode: lets the user refresh the collection using his own function.
   *
   * See Expose $select.search for external / remote filtering https://github.com/angular-ui/ui-select/pull/31
   */
  ctrl.refresh = function(refreshAttr) {
    if (refreshAttr !== undefined) {
      // Debounce
      // See https://github.com/angular-ui/bootstrap/blob/0.10.0/src/typeahead/typeahead.js#L155
      // FYI AngularStrap typeahead does not have debouncing: https://github.com/mgcrea/angular-strap/blob/v2.0.0-rc.4/src/typeahead/typeahead.js#L177
      if (_refreshDelayPromise) {
        $timeout.cancel(_refreshDelayPromise);
      }
      _refreshDelayPromise = $timeout(function() {
        if ($scope.$select.search.length >= $scope.$select.minimumInputLength) {
          var refreshPromise = $scope.$eval(refreshAttr);
          if (refreshPromise && angular.isFunction(refreshPromise.then) && !ctrl.refreshing) {
            ctrl.refreshing = true;
            refreshPromise.finally(function() {
              ctrl.refreshing = false;
            });
          }
        }
      }, ctrl.refreshDelay);
    }
  };

  ctrl.isActive = function(itemScope) {
    if ( !ctrl.open ) {
      return false;
    }
    var itemIndex = ctrl.items.indexOf(itemScope[ctrl.itemProperty]);
    var isActive =  itemIndex == ctrl.activeIndex;

    if ( !isActive || itemIndex < 0 ) {
      return false;
    }

    if (isActive && !angular.isUndefined(ctrl.onHighlightCallback)) {
      itemScope.$eval(ctrl.onHighlightCallback);
    }

    return isActive;
  };

  var _isItemSelected = function (item) {
    return (ctrl.selected && angular.isArray(ctrl.selected) &&
        ctrl.selected.filter(function (selection) { return angular.equals(selection, item); }).length > 0);
  };

  var disabledItems = [];

  function _updateItemDisabled(item, isDisabled) {
    var disabledItemIndex = disabledItems.indexOf(item);
    if (isDisabled && disabledItemIndex === -1) {
      disabledItems.push(item);
    }

    if (!isDisabled && disabledItemIndex > -1) {
      disabledItems.splice(disabledItemIndex, 1);
    }
  }

  function _isItemDisabled(item) {
    return disabledItems.indexOf(item) > -1;
  }

  ctrl.isDisabled = function(itemScope) {

    if (!ctrl.open) return;

    var item = itemScope[ctrl.itemProperty];
    var itemIndex = ctrl.items.indexOf(item);
    var isDisabled = false;

    if (itemIndex >= 0 && (angular.isDefined(ctrl.disableChoiceExpression) || ctrl.multiple)) {

      if (item.isTag) return false;

      if (ctrl.multiple) {
        isDisabled = _isItemSelected(item);
      }

      if (!isDisabled && angular.isDefined(ctrl.disableChoiceExpression)) {
        isDisabled = !!(itemScope.$eval(ctrl.disableChoiceExpression));
      }

      _updateItemDisabled(item, isDisabled);
    }

    return isDisabled;
  };


  // When the user selects an item with ENTER or clicks the dropdown
  ctrl.select = function(item, skipFocusser, $event) {
    if (isNil(item) || !_isItemDisabled(item)) {

      if ( ! ctrl.items && ! ctrl.search && ! ctrl.tagging.isActivated) return;

      if (!item || !_isItemDisabled(item)) {
        // if click is made on existing item, prevent from tagging, ctrl.search does not matter
        ctrl.clickTriggeredSelect = false;
        if($event && ($event.type === 'click' || $event.type === 'touchend') && item)
          ctrl.clickTriggeredSelect = true;

        if(ctrl.tagging.isActivated && ctrl.clickTriggeredSelect === false) {
          // if taggingLabel is disabled and item is undefined we pull from ctrl.search
          if ( ctrl.taggingLabel === false ) {
            if ( ctrl.activeIndex < 0 ) {
              if (item === undefined) {
                item = ctrl.tagging.fct !== undefined ? ctrl.tagging.fct(ctrl.search) : ctrl.search;
              }
              if (!item || angular.equals( ctrl.items[0], item ) ) {
                return;
              }
            } else {
              // keyboard nav happened first, user selected from dropdown
              item = ctrl.items[ctrl.activeIndex];
            }
          } else {
            // tagging always operates at index zero, taggingLabel === false pushes
            // the ctrl.search value without having it injected
            if ( ctrl.activeIndex === 0 ) {
              // ctrl.tagging pushes items to ctrl.items, so we only have empty val
              // for `item` if it is a detected duplicate
              if ( item === undefined ) return;

              // create new item on the fly if we don't already have one;
              // use tagging function if we have one
              if ( ctrl.tagging.fct !== undefined && typeof item === 'string' ) {
                item = ctrl.tagging.fct(item);
                if (!item) return;
              // if item type is 'string', apply the tagging label
              } else if ( typeof item === 'string' ) {
                // trim the trailing space
                item = item.replace(ctrl.taggingLabel,'').trim();
              }
            }
          }
          // search ctrl.selected for dupes potentially caused by tagging and return early if found
          if (_isItemSelected(item)) {
            ctrl.close(skipFocusser);
            return;
          }
        }
        _resetSearchInput();
        $scope.$broadcast('uis:select', item);

        if (ctrl.closeOnSelect) {
          ctrl.close(skipFocusser);
        }
      }
    }
  };

  // Closes the dropdown
  ctrl.close = function(skipFocusser) {
    if (!ctrl.open) return;
    if (ctrl.ngModel && ctrl.ngModel.$setTouched) ctrl.ngModel.$setTouched();
    ctrl.open = false;
    _resetSearchInput();
    $scope.$broadcast('uis:close', skipFocusser);

  };

  ctrl.setFocus = function(){
    if (!ctrl.focus) ctrl.focusInput[0].focus();
  };

  ctrl.clear = function($event) {
    ctrl.select(null);
    $event.stopPropagation();
    $timeout(function() {
      ctrl.focusser[0].focus();
    }, 0, false);
  };

  // Toggle dropdown
  ctrl.toggle = function(e) {
    if (ctrl.open) {
      ctrl.close();
      e.preventDefault();
      e.stopPropagation();
    } else {
      ctrl.activate();
    }
  };

  // Set default function for locked choices - avoids unnecessary
  // logic if functionality is not being used
  ctrl.isLocked = function () {
    return false;
  };

  $scope.$watch(function () {
    return angular.isDefined(ctrl.lockChoiceExpression) && ctrl.lockChoiceExpression !== "";
  }, _initaliseLockedChoices);

  function _initaliseLockedChoices(doInitalise) {
    if(!doInitalise) return;

    var lockedItems = [];

    function _updateItemLocked(item, isLocked) {
      var lockedItemIndex = lockedItems.indexOf(item);
      if (isLocked && lockedItemIndex === -1) {
        lockedItems.push(item);
        }

      if (!isLocked && lockedItemIndex > -1) {
        lockedItems.splice(lockedItemIndex, 1);
      }
    }

    function _isItemlocked(item) {
      return lockedItems.indexOf(item) > -1;
    }

    ctrl.isLocked = function (itemScope, itemIndex) {
      var isLocked = false,
          item = ctrl.selected[itemIndex];

      if(item) {
        if (itemScope) {
          isLocked = !!(itemScope.$eval(ctrl.lockChoiceExpression));
          _updateItemLocked(item, isLocked);
        } else {
          isLocked = _isItemlocked(item);
        }
      }

      return isLocked;
    };
  }


  var sizeWatch = null;
  var updaterScheduled = false;
  ctrl.sizeSearchInput = function() {

    var input = ctrl.searchInput[0],
        container = ctrl.$element[0],
        calculateContainerWidth = function() {
          // Return the container width only if the search input is visible
          return container.clientWidth * !!input.offsetParent;
        },
        updateIfVisible = function(containerWidth) {
          if (containerWidth === 0) {
            return false;
          }
          var inputWidth = containerWidth - input.offsetLeft;
          if (inputWidth < 50) inputWidth = containerWidth;
          ctrl.searchInput.css('width', inputWidth+'px');
          return true;
        };

    ctrl.searchInput.css('width', '10px');
    $timeout(function() { //Give tags time to render correctly
      if (sizeWatch === null && !updateIfVisible(calculateContainerWidth())) {
        sizeWatch = $scope.$watch(function() {
          if (!updaterScheduled) {
            updaterScheduled = true;
            $scope.$$postDigest(function() {
              updaterScheduled = false;
              if (updateIfVisible(calculateContainerWidth())) {
                sizeWatch();
                sizeWatch = null;
              }
            });
          }
        }, angular.noop);
      }
    });
  };

  function _handleDropDownSelection(key) {
    var processed = true;
    switch (key) {
      case KEY.DOWN:
        if (!ctrl.open && ctrl.multiple) ctrl.activate(false, true); //In case its the search input in 'multiple' mode
        else if (ctrl.activeIndex < ctrl.items.length - 1) {
          var idx = ++ctrl.activeIndex;
          while(_isItemDisabled(ctrl.items[idx]) && idx < ctrl.items.length) {
            ctrl.activeIndex = ++idx;
          }
        }
        break;
      case KEY.UP:
        var minActiveIndex = (ctrl.search.length === 0 && ctrl.tagging.isActivated) ? -1 : 0;
        if (!ctrl.open && ctrl.multiple) ctrl.activate(false, true); //In case its the search input in 'multiple' mode
        else if (ctrl.activeIndex > minActiveIndex) {
          var idxmin = --ctrl.activeIndex;
          while(_isItemDisabled(ctrl.items[idxmin]) && idxmin > minActiveIndex) {
            ctrl.activeIndex = --idxmin;
          }
        }
        break;
      case KEY.TAB:
        if (!ctrl.multiple || ctrl.open) ctrl.select(ctrl.items[ctrl.activeIndex], true);
        break;
      case KEY.ENTER:
        if(ctrl.open && (ctrl.tagging.isActivated || ctrl.activeIndex >= 0)){
          ctrl.select(ctrl.items[ctrl.activeIndex], ctrl.skipFocusser); // Make sure at least one dropdown item is highlighted before adding if not in tagging mode
        } else {
          ctrl.activate(false, true); //In case its the search input in 'multiple' mode
        }
        break;
      case KEY.ESC:
        ctrl.close();
        break;
      default:
        processed = false;
    }
    return processed;
  }

  // Bind to keyboard shortcuts
  ctrl.searchInput.on('keydown', function(e) {

    var key = e.which;

    if (~[KEY.ENTER,KEY.ESC].indexOf(key)){
      e.preventDefault();
      e.stopPropagation();
    }

    $scope.$apply(function() {

      var tagged = false;

      if (ctrl.items.length > 0 || ctrl.tagging.isActivated) {
        if(!_handleDropDownSelection(key) && !ctrl.searchEnabled) {
          e.preventDefault();
          e.stopPropagation();
        }
        if ( ctrl.taggingTokens.isActivated ) {
          for (var i = 0; i < ctrl.taggingTokens.tokens.length; i++) {
            if ( ctrl.taggingTokens.tokens[i] === KEY.MAP[e.keyCode] ) {
              // make sure there is a new value to push via tagging
              if ( ctrl.search.length > 0 ) {
                tagged = true;
              }
            }
          }
          if ( tagged ) {
            $timeout(function() {
              ctrl.searchInput.triggerHandler('tagged');
              var newItem = ctrl.search.replace(KEY.MAP[e.keyCode],'').trim();
              if ( ctrl.tagging.fct ) {
                newItem = ctrl.tagging.fct( newItem );
              }
              if (newItem) ctrl.select(newItem, true);
            });
          }
        }
      }

    });

    if(KEY.isVerticalMovement(key) && ctrl.items.length > 0){
      _ensureHighlightVisible();
    }

    if (key === KEY.ENTER || key === KEY.ESC) {
      e.preventDefault();
      e.stopPropagation();
    }

  });

  ctrl.searchInput.on('paste', function (e) {
    var data;

    if (window.clipboardData && window.clipboardData.getData) { // IE
      data = window.clipboardData.getData('Text');
    } else {
      data = (e.originalEvent || e).clipboardData.getData('text/plain');
    }

    // Prepend the current input field text to the paste buffer.
    data = ctrl.search + data;

    if (data && data.length > 0) {
      // If tagging try to split by tokens and add items
      if (ctrl.taggingTokens.isActivated) {
        var items = [];
        for (var i = 0; i < ctrl.taggingTokens.tokens.length; i++) {  // split by first token that is contained in data
          var separator = KEY.toSeparator(ctrl.taggingTokens.tokens[i]) || ctrl.taggingTokens.tokens[i];
          if (data.indexOf(separator) > -1) {
            items = data.split(separator);
            break;  // only split by one token
          }
        }
        if (items.length === 0) {
          items = [data];
        }
        var oldsearch = ctrl.search;
        angular.forEach(items, function (item) {
          var newItem = ctrl.tagging.fct ? ctrl.tagging.fct(item) : item;
          if (newItem) {
            ctrl.select(newItem, true);
          }
        });
        ctrl.search = oldsearch || EMPTY_SEARCH;
        e.preventDefault();
        e.stopPropagation();
      } else if (ctrl.paste) {
        ctrl.paste(data);
        ctrl.search = EMPTY_SEARCH;
        e.preventDefault();
        e.stopPropagation();
      }
    }
  });

  ctrl.searchInput.on('tagged', function() {
    $timeout(function() {
      _resetSearchInput();
    });
  });

  // See https://github.com/ivaynberg/select2/blob/3.4.6/select2.js#L1431
  function _ensureHighlightVisible() {
    var container = $element.querySelectorAll('.ui-select-choices-content');
    var choices = container.querySelectorAll('.ui-select-choices-row');
    if (choices.length < 1) {
      throw uiSelectMinErr('choices', "Expected multiple .ui-select-choices-row but got '{0}'.", choices.length);
    }

    if (ctrl.activeIndex < 0) {
      return;
    }

    var highlighted = choices[ctrl.activeIndex];
    var posY = highlighted.offsetTop + highlighted.clientHeight - container[0].scrollTop;
    var height = container[0].offsetHeight;

    if (posY > height) {
      container[0].scrollTop += posY - height;
    } else if (posY < highlighted.clientHeight) {
      if (ctrl.isGrouped && ctrl.activeIndex === 0)
        container[0].scrollTop = 0; //To make group header visible when going all the way up
      else
        container[0].scrollTop -= highlighted.clientHeight - posY;
    }
  }

  var onResize = $$uisDebounce(function() {
    ctrl.sizeSearchInput();
  }, 50);

  angular.element($window).bind('resize', onResize);

  $scope.$on('$destroy', function() {
    ctrl.searchInput.off('keyup keydown tagged blur paste');
    angular.element($window).off('resize', onResize);
  });

  $scope.$watch('$select.activeIndex', function(activeIndex) {
    if (activeIndex)
      $element.find('input').attr(
        'aria-activedescendant',
        'ui-select-choices-row-' + ctrl.generatedId + '-' + activeIndex);
  });

  $scope.$watch('$select.open', function(open) {
    if (!open)
      $element.find('input').removeAttr('aria-activedescendant');
  });
}]);

uis.directive('uiSelect',
  ['$document', 'uiSelectConfig', 'uiSelectMinErr', 'uisOffset', '$compile', '$parse', '$timeout',
  function($document, uiSelectConfig, uiSelectMinErr, uisOffset, $compile, $parse, $timeout) {

  return {
    restrict: 'EA',
    templateUrl: function(tElement, tAttrs) {
      var theme = tAttrs.theme || uiSelectConfig.theme;
      return theme + (angular.isDefined(tAttrs.multiple) ? '/select-multiple.tpl.html' : '/select.tpl.html');
    },
    replace: true,
    transclude: true,
    require: ['uiSelect', '^ngModel'],
    scope: true,

    controller: 'uiSelectCtrl',
    controllerAs: '$select',
    compile: function(tElement, tAttrs) {

      // Allow setting ngClass on uiSelect
      var match = /{(.*)}\s*{(.*)}/.exec(tAttrs.ngClass);
      if(match) {
        var combined = '{'+ match[1] +', '+ match[2] +'}';
        tAttrs.ngClass = combined;
        tElement.attr('ng-class', combined);
      }

      //Multiple or Single depending if multiple attribute presence
      if (angular.isDefined(tAttrs.multiple))
        tElement.append('<ui-select-multiple/>').removeAttr('multiple');
      else
        tElement.append('<ui-select-single/>');

      if (tAttrs.inputId)
        tElement.querySelectorAll('input.ui-select-search')[0].id = tAttrs.inputId;

      return function(scope, element, attrs, ctrls, transcludeFn) {

        var $select = ctrls[0];
        var ngModel = ctrls[1];

        $select.generatedId = uiSelectConfig.generateId();
        $select.baseTitle = attrs.title || 'Select box';
        $select.focusserTitle = $select.baseTitle + ' focus';
        $select.focusserId = 'focusser-' + $select.generatedId;

        $select.closeOnSelect = function() {
          if (angular.isDefined(attrs.closeOnSelect)) {
            return $parse(attrs.closeOnSelect)();
          } else {
            return uiSelectConfig.closeOnSelect;
          }
        }();

        scope.$watch('skipFocusser', function() {
            var skipFocusser = scope.$eval(attrs.skipFocusser);
            $select.skipFocusser = skipFocusser !== undefined ? skipFocusser : uiSelectConfig.skipFocusser;
        });

        $select.onSelectCallback = $parse(attrs.onSelect);
        $select.onRemoveCallback = $parse(attrs.onRemove);

        //Set reference to ngModel from uiSelectCtrl
        $select.ngModel = ngModel;

        $select.choiceGrouped = function(group){
          return $select.isGrouped && group && group.name;
        };

        if(attrs.tabindex){
          attrs.$observe('tabindex', function(value) {
            $select.focusInput.attr('tabindex', value);
            element.removeAttr('tabindex');
          });
        }

        scope.$watch(function () { return scope.$eval(attrs.searchEnabled); }, function(newVal) {
          $select.searchEnabled = newVal !== undefined ? newVal : uiSelectConfig.searchEnabled;
        });

        scope.$watch('sortable', function() {
            var sortable = scope.$eval(attrs.sortable);
            $select.sortable = sortable !== undefined ? sortable : uiSelectConfig.sortable;
        });

        attrs.$observe('backspaceReset', function() {
          // $eval() is needed otherwise we get a string instead of a boolean
          var backspaceReset = scope.$eval(attrs.backspaceReset);
          $select.backspaceReset = backspaceReset !== undefined ? backspaceReset : true;
        });

        attrs.$observe('limit', function() {
          //Limit the number of selections allowed
          $select.limit = (angular.isDefined(attrs.limit)) ? parseInt(attrs.limit, 10) : undefined;
        });

        scope.$watch('removeSelected', function() {
            var removeSelected = scope.$eval(attrs.removeSelected);
            $select.removeSelected = removeSelected !== undefined ? removeSelected : uiSelectConfig.removeSelected;
        });

        attrs.$observe('disabled', function() {
          // No need to use $eval() (thanks to ng-disabled) since we already get a boolean instead of a string
          $select.disabled = attrs.disabled !== undefined ? attrs.disabled : false;
        });

        attrs.$observe('resetSearchInput', function() {
          // $eval() is needed otherwise we get a string instead of a boolean
          var resetSearchInput = scope.$eval(attrs.resetSearchInput);
          $select.resetSearchInput = resetSearchInput !== undefined ? resetSearchInput : true;
        });

        attrs.$observe('paste', function() {
          $select.paste = scope.$eval(attrs.paste);
        });

        attrs.$observe('tagging', function() {
          if(attrs.tagging !== undefined)
          {
            // $eval() is needed otherwise we get a string instead of a boolean
            var taggingEval = scope.$eval(attrs.tagging);
            $select.tagging = {isActivated: true, fct: taggingEval !== true ? taggingEval : undefined};
          }
          else
          {
            $select.tagging = {isActivated: false, fct: undefined};
          }
        });

        attrs.$observe('taggingLabel', function() {
          if(attrs.tagging !== undefined )
          {
            // check eval for FALSE, in this case, we disable the labels
            // associated with tagging
            if ( attrs.taggingLabel === 'false' ) {
              $select.taggingLabel = false;
            }
            else
            {
              $select.taggingLabel = attrs.taggingLabel !== undefined ? attrs.taggingLabel : '(new)';
            }
          }
        });

        attrs.$observe('taggingTokens', function() {
          if (attrs.tagging !== undefined) {
            var tokens = attrs.taggingTokens !== undefined ? attrs.taggingTokens.split('|') : [',','ENTER'];
            $select.taggingTokens = {isActivated: true, tokens: tokens };
          }
        });

        attrs.$observe('spinnerEnabled', function() {
          // $eval() is needed otherwise we get a string instead of a boolean
          var spinnerEnabled = scope.$eval(attrs.spinnerEnabled);
          $select.spinnerEnabled = spinnerEnabled !== undefined ? spinnerEnabled : uiSelectConfig.spinnerEnabled;
        });

        attrs.$observe('spinnerClass', function() {
          var spinnerClass = attrs.spinnerClass;
          $select.spinnerClass = spinnerClass !== undefined ? attrs.spinnerClass : uiSelectConfig.spinnerClass;
        });

        //Automatically gets focus when loaded
        if (angular.isDefined(attrs.autofocus)){
          $timeout(function(){
            $select.setFocus();
          });
        }

        //Gets focus based on scope event name (e.g. focus-on='SomeEventName')
        if (angular.isDefined(attrs.focusOn)){
          scope.$on(attrs.focusOn, function() {
              $timeout(function(){
                $select.setFocus();
              });
          });
        }

        function onDocumentClick(e) {
          if (!$select.open) return; //Skip it if dropdown is close

          var contains = false;

          if (window.jQuery) {
            // Firefox 3.6 does not support element.contains()
            // See Node.contains https://developer.mozilla.org/en-US/docs/Web/API/Node.contains
            contains = window.jQuery.contains(element[0], e.target);
          } else {
            contains = element[0].contains(e.target);
          }

          if (!contains && !$select.clickTriggeredSelect) {
            var skipFocusser;
            if (!$select.skipFocusser) {
              //Will lose focus only with certain targets
              var focusableControls = ['input','button','textarea','select'];
              var targetController = angular.element(e.target).controller('uiSelect'); //To check if target is other ui-select
              skipFocusser = targetController && targetController !== $select; //To check if target is other ui-select
              if (!skipFocusser) skipFocusser =  ~focusableControls.indexOf(e.target.tagName.toLowerCase()); //Check if target is input, button or textarea
            } else {
              skipFocusser = true;
            }
            $select.close(skipFocusser);
            scope.$digest();
          }
          $select.clickTriggeredSelect = false;
        }

        // See Click everywhere but here event http://stackoverflow.com/questions/12931369
        $document.on('click', onDocumentClick);

        scope.$on('$destroy', function() {
          $document.off('click', onDocumentClick);
        });

        // Move transcluded elements to their correct position in main template
        transcludeFn(scope, function(clone) {
          // See Transclude in AngularJS http://blog.omkarpatil.com/2012/11/transclude-in-angularjs.html

          // One day jqLite will be replaced by jQuery and we will be able to write:
          // var transcludedElement = clone.filter('.my-class')
          // instead of creating a hackish DOM element:
          var transcluded = angular.element('<div>').append(clone);

          var transcludedMatch = transcluded.querySelectorAll('.ui-select-match');
          transcludedMatch.removeAttr('ui-select-match'); //To avoid loop in case directive as attr
          transcludedMatch.removeAttr('data-ui-select-match'); // Properly handle HTML5 data-attributes
          if (transcludedMatch.length !== 1) {
            throw uiSelectMinErr('transcluded', "Expected 1 .ui-select-match but got '{0}'.", transcludedMatch.length);
          }
          element.querySelectorAll('.ui-select-match').replaceWith(transcludedMatch);

          var transcludedChoices = transcluded.querySelectorAll('.ui-select-choices');
          transcludedChoices.removeAttr('ui-select-choices'); //To avoid loop in case directive as attr
          transcludedChoices.removeAttr('data-ui-select-choices'); // Properly handle HTML5 data-attributes
          if (transcludedChoices.length !== 1) {
            throw uiSelectMinErr('transcluded', "Expected 1 .ui-select-choices but got '{0}'.", transcludedChoices.length);
          }
          element.querySelectorAll('.ui-select-choices').replaceWith(transcludedChoices);

          var transcludedNoChoice = transcluded.querySelectorAll('.ui-select-no-choice');
          transcludedNoChoice.removeAttr('ui-select-no-choice'); //To avoid loop in case directive as attr
          transcludedNoChoice.removeAttr('data-ui-select-no-choice'); // Properly handle HTML5 data-attributes
          if (transcludedNoChoice.length == 1) {
            element.querySelectorAll('.ui-select-no-choice').replaceWith(transcludedNoChoice);
          }
        });

        // Support for appending the select field to the body when its open
        var appendToBody = scope.$eval(attrs.appendToBody);
        if (appendToBody !== undefined ? appendToBody : uiSelectConfig.appendToBody) {
          scope.$watch('$select.open', function(isOpen) {
            if (isOpen) {
              positionDropdown();
            } else {
              resetDropdown();
            }
          });

          // Move the dropdown back to its original location when the scope is destroyed. Otherwise
          // it might stick around when the user routes away or the select field is otherwise removed
          scope.$on('$destroy', function() {
            resetDropdown();
          });
        }

        // Hold on to a reference to the .ui-select-container element for appendToBody support
        var placeholder = null,
            originalWidth = '';

        function positionDropdown() {
          // Remember the absolute position of the element
          var offset = uisOffset(element);

          // Clone the element into a placeholder element to take its original place in the DOM
          placeholder = angular.element('<div class="ui-select-placeholder"></div>');
          placeholder[0].style.width = offset.width + 'px';
          placeholder[0].style.height = offset.height + 'px';
          element.after(placeholder);

          // Remember the original value of the element width inline style, so it can be restored
          // when the dropdown is closed
          originalWidth = element[0].style.width;

          // Now move the actual dropdown element to the end of the body
          $document.find('body').append(element);

          element[0].style.position = 'absolute';
          element[0].style.left = offset.left + 'px';
          element[0].style.top = offset.top + 'px';
          element[0].style.width = offset.width + 'px';
        }

        function resetDropdown() {
          if (placeholder === null) {
            // The dropdown has not actually been display yet, so there's nothing to reset
            return;
          }

          // Move the dropdown element back to its original location in the DOM
          placeholder.replaceWith(element);
          placeholder = null;

          element[0].style.position = '';
          element[0].style.left = '';
          element[0].style.top = '';
          element[0].style.width = originalWidth;

          // Set focus back on to the moved element
          $select.setFocus();
        }

        // Hold on to a reference to the .ui-select-dropdown element for direction support.
        var dropdown = null,
            directionUpClassName = 'direction-up';

        // Support changing the direction of the dropdown if there isn't enough space to render it.
        scope.$watch('$select.open', function() {

          if ($select.dropdownPosition === 'auto' || $select.dropdownPosition === 'up'){
            scope.calculateDropdownPos();
          }

        });

        var setDropdownPosUp = function(offset, offsetDropdown){

          offset = offset || uisOffset(element);
          offsetDropdown = offsetDropdown || uisOffset(dropdown);

          dropdown[0].style.position = 'absolute';
          dropdown[0].style.top = (offsetDropdown.height * -1) + 'px';
          element.addClass(directionUpClassName);

        };

        var setDropdownPosDown = function(offset, offsetDropdown){

          element.removeClass(directionUpClassName);

          offset = offset || uisOffset(element);
          offsetDropdown = offsetDropdown || uisOffset(dropdown);

          dropdown[0].style.position = '';
          dropdown[0].style.top = '';

        };

        var calculateDropdownPosAfterAnimation = function() {
          // Delay positioning the dropdown until all choices have been added so its height is correct.
          $timeout(function() {
            if ($select.dropdownPosition === 'up') {
              //Go UP
              setDropdownPosUp();
            } else {
              //AUTO
              element.removeClass(directionUpClassName);

              var offset = uisOffset(element);
              var offsetDropdown = uisOffset(dropdown);

              //https://code.google.com/p/chromium/issues/detail?id=342307#c4
              var scrollTop = $document[0].documentElement.scrollTop || $document[0].body.scrollTop; //To make it cross browser (blink, webkit, IE, Firefox).

              // Determine if the direction of the dropdown needs to be changed.
              if (offset.top + offset.height + offsetDropdown.height > scrollTop + $document[0].documentElement.clientHeight) {
                //Go UP
                setDropdownPosUp(offset, offsetDropdown);
              }else{
                //Go DOWN
                setDropdownPosDown(offset, offsetDropdown);
              }
            }

            // Display the dropdown once it has been positioned.
            dropdown[0].style.opacity = 1;
          });
        };

        var opened = false;

        scope.calculateDropdownPos = function() {
          if ($select.open) {
            dropdown = angular.element(element).querySelectorAll('.ui-select-dropdown');

            if (dropdown.length === 0) {
              return;
            }

           // Hide the dropdown so there is no flicker until $timeout is done executing.
           if ($select.search === '' && !opened) {
              dropdown[0].style.opacity = 0;
              opened = true;
           }

            if (!uisOffset(dropdown).height && $select.$animate && $select.$animate.on && $select.$animate.enabled(dropdown)) {
              var needsCalculated = true;

              $select.$animate.on('enter', dropdown, function (elem, phase) {
                if (phase === 'close' && needsCalculated) {
                  calculateDropdownPosAfterAnimation();
                  needsCalculated = false;
                }
              });
            } else {
              calculateDropdownPosAfterAnimation();
            }
          } else {
            if (dropdown === null || dropdown.length === 0) {
              return;
            }

            // Reset the position of the dropdown.
            dropdown[0].style.opacity = 0;
            dropdown[0].style.position = '';
            dropdown[0].style.top = '';
            element.removeClass(directionUpClassName);
          }
        };
      };
    }
  };
}]);

uis.directive('uiSelectMatch', ['uiSelectConfig', function(uiSelectConfig) {
  return {
    restrict: 'EA',
    require: '^uiSelect',
    replace: true,
    transclude: true,
    templateUrl: function(tElement) {
      // Needed so the uiSelect can detect the transcluded content
      tElement.addClass('ui-select-match');

      var parent = tElement.parent();
      // Gets theme attribute from parent (ui-select)
      var theme = getAttribute(parent, 'theme') || uiSelectConfig.theme;
      var multi = angular.isDefined(getAttribute(parent, 'multiple'));

      return theme + (multi ? '/match-multiple.tpl.html' : '/match.tpl.html');
    },
    link: function(scope, element, attrs, $select) {
      $select.lockChoiceExpression = attrs.uiLockChoice;
      attrs.$observe('placeholder', function(placeholder) {
        $select.placeholder = placeholder !== undefined ? placeholder : uiSelectConfig.placeholder;
      });

      function setAllowClear(allow) {
        $select.allowClear = (angular.isDefined(allow)) ? (allow === '') ? true : (allow.toLowerCase() === 'true') : false;
      }

      attrs.$observe('allowClear', setAllowClear);
      setAllowClear(attrs.allowClear);

      if($select.multiple){
        $select.sizeSearchInput();
      }

    }
  };

  function getAttribute(elem, attribute) {
    if (elem[0].hasAttribute(attribute))
      return elem.attr(attribute);

    if (elem[0].hasAttribute('data-' + attribute))
      return elem.attr('data-' + attribute);

    if (elem[0].hasAttribute('x-' + attribute))
      return elem.attr('x-' + attribute);
  }
}]);

uis.directive('uiSelectMultiple', ['uiSelectMinErr','$timeout', function(uiSelectMinErr, $timeout) {
  return {
    restrict: 'EA',
    require: ['^uiSelect', '^ngModel'],

    controller: ['$scope','$timeout', function($scope, $timeout){

      var ctrl = this,
          $select = $scope.$select,
          ngModel;

      if (angular.isUndefined($select.selected))
        $select.selected = [];

      //Wait for link fn to inject it
      $scope.$evalAsync(function(){ ngModel = $scope.ngModel; });

      ctrl.activeMatchIndex = -1;

      ctrl.updateModel = function(){
        ngModel.$setViewValue(Date.now()); //Set timestamp as a unique string to force changes
        ctrl.refreshComponent();
      };

      ctrl.refreshComponent = function(){
        //Remove already selected items
        //e.g. When user clicks on a selection, the selected array changes and
        //the dropdown should remove that item
        if($select.refreshItems){
          $select.refreshItems();
        }
        if($select.sizeSearchInput){
          $select.sizeSearchInput();
        }
      };

      // Remove item from multiple select
      ctrl.removeChoice = function(index){

        // if the choice is locked, don't remove it
        if($select.isLocked(null, index)) return false;

        var removedChoice = $select.selected[index];

        var locals = {};
        locals[$select.parserResult.itemName] = removedChoice;

        $select.selected.splice(index, 1);
        ctrl.activeMatchIndex = -1;
        $select.sizeSearchInput();

        // Give some time for scope propagation.
        $timeout(function(){
          $select.onRemoveCallback($scope, {
            $item: removedChoice,
            $model: $select.parserResult.modelMapper($scope, locals)
          });
        });

        ctrl.updateModel();

        return true;
      };

      ctrl.getPlaceholder = function(){
        //Refactor single?
        if($select.selected && $select.selected.length) return;
        return $select.placeholder;
      };


    }],
    controllerAs: '$selectMultiple',

    link: function(scope, element, attrs, ctrls) {

      var $select = ctrls[0];
      var ngModel = scope.ngModel = ctrls[1];
      var $selectMultiple = scope.$selectMultiple;

      //$select.selected = raw selected objects (ignoring any property binding)

      $select.multiple = true;

      //Input that will handle focus
      $select.focusInput = $select.searchInput;

      //Properly check for empty if set to multiple
      ngModel.$isEmpty = function(value) {
        return !value || value.length === 0;
      };

      //From view --> model
      ngModel.$parsers.unshift(function () {
        var locals = {},
            result,
            resultMultiple = [];
        for (var j = $select.selected.length - 1; j >= 0; j--) {
          locals = {};
          locals[$select.parserResult.itemName] = $select.selected[j];
          result = $select.parserResult.modelMapper(scope, locals);
          resultMultiple.unshift(result);
        }
        return resultMultiple;
      });

      // From model --> view
      ngModel.$formatters.unshift(function (inputValue) {
        var data = $select.parserResult && $select.parserResult.source (scope, { $select : {search:''}}), //Overwrite $search
            locals = {},
            result;
        if (!data) return inputValue;
        var resultMultiple = [];
        var checkFnMultiple = function(list, value){
          if (!list || !list.length) return;
          for (var p = list.length - 1; p >= 0; p--) {
            locals[$select.parserResult.itemName] = list[p];
            result = $select.parserResult.modelMapper(scope, locals);
            if($select.parserResult.trackByExp){
                var propsItemNameMatches = /(\w*)\./.exec($select.parserResult.trackByExp);
                var matches = /\.([^\s]+)/.exec($select.parserResult.trackByExp);
                if(propsItemNameMatches && propsItemNameMatches.length > 0 && propsItemNameMatches[1] == $select.parserResult.itemName){
                  if(matches && matches.length>0 && result[matches[1]] == value[matches[1]]){
                      resultMultiple.unshift(list[p]);
                      return true;
                  }
                }
            }
            if (angular.equals(result,value)){
              resultMultiple.unshift(list[p]);
              return true;
            }
          }
          return false;
        };
        if (!inputValue) return resultMultiple; //If ngModel was undefined
        for (var k = inputValue.length - 1; k >= 0; k--) {
          //Check model array of currently selected items
          if (!checkFnMultiple($select.selected, inputValue[k])){
            //Check model array of all items available
            if (!checkFnMultiple(data, inputValue[k])){
              //If not found on previous lists, just add it directly to resultMultiple
              resultMultiple.unshift(inputValue[k]);
            }
          }
        }
        return resultMultiple;
      });

      //Watch for external model changes
      scope.$watchCollection(function(){ return ngModel.$modelValue; }, function(newValue, oldValue) {
        if (oldValue != newValue){
          //update the view value with fresh data from items, if there is a valid model value
          if(angular.isDefined(ngModel.$modelValue)) {
            ngModel.$modelValue = null; //Force scope model value and ngModel value to be out of sync to re-run formatters
          }
          $selectMultiple.refreshComponent();
        }
      });

      ngModel.$render = function() {
        // Make sure that model value is array
        if(!angular.isArray(ngModel.$viewValue)){
          // Have tolerance for null or undefined values
          if (isNil(ngModel.$viewValue)){
            ngModel.$viewValue = [];
          } else {
            throw uiSelectMinErr('multiarr', "Expected model value to be array but got '{0}'", ngModel.$viewValue);
          }
        }
        $select.selected = ngModel.$viewValue;
        $selectMultiple.refreshComponent();
        scope.$evalAsync(); //To force $digest
      };

      scope.$on('uis:select', function (event, item) {
        if($select.selected.length >= $select.limit) {
          return;
        }
        $select.selected.push(item);
        var locals = {};
        locals[$select.parserResult.itemName] = item;

        $timeout(function(){
          $select.onSelectCallback(scope, {
            $item: item,
            $model: $select.parserResult.modelMapper(scope, locals)
          });
        });
        $selectMultiple.updateModel();
      });

      scope.$on('uis:activate', function () {
        $selectMultiple.activeMatchIndex = -1;
      });

      scope.$watch('$select.disabled', function(newValue, oldValue) {
        // As the search input field may now become visible, it may be necessary to recompute its size
        if (oldValue && !newValue) $select.sizeSearchInput();
      });

      $select.searchInput.on('keydown', function(e) {
        var key = e.which;
        scope.$apply(function() {
          var processed = false;
          // var tagged = false; //Checkme
          if(KEY.isHorizontalMovement(key)){
            processed = _handleMatchSelection(key);
          }
          if (processed  && key != KEY.TAB) {
            //TODO Check si el tab selecciona aun correctamente
            //Crear test
            e.preventDefault();
            e.stopPropagation();
          }
        });
      });
      function _getCaretPosition(el) {
        if(angular.isNumber(el.selectionStart)) return el.selectionStart;
        // selectionStart is not supported in IE8 and we don't want hacky workarounds so we compromise
        else return el.value.length;
      }
      // Handles selected options in "multiple" mode
      function _handleMatchSelection(key){
        var caretPosition = _getCaretPosition($select.searchInput[0]),
            length = $select.selected.length,
            // none  = -1,
            first = 0,
            last  = length-1,
            curr  = $selectMultiple.activeMatchIndex,
            next  = $selectMultiple.activeMatchIndex+1,
            prev  = $selectMultiple.activeMatchIndex-1,
            newIndex = curr;

        if(caretPosition > 0 || ($select.search.length && key == KEY.RIGHT)) return false;

        $select.close();

        function getNewActiveMatchIndex(){
          switch(key){
            case KEY.LEFT:
              // Select previous/first item
              if(~$selectMultiple.activeMatchIndex) return prev;
              // Select last item
              else return last;
              break;
            case KEY.RIGHT:
              // Open drop-down
              if(!~$selectMultiple.activeMatchIndex || curr === last){
                $select.activate();
                return false;
              }
              // Select next/last item
              else return next;
              break;
            case KEY.BACKSPACE:
              // Remove selected item and select previous/first
              if(~$selectMultiple.activeMatchIndex){
                if($selectMultiple.removeChoice(curr)) {
                  return prev;
                } else {
                  return curr;
                }

              } else {
                // If nothing yet selected, select last item
                return last;
              }
              break;
            case KEY.DELETE:
              // Remove selected item and select next item
              if(~$selectMultiple.activeMatchIndex){
                $selectMultiple.removeChoice($selectMultiple.activeMatchIndex);
                return curr;
              }
              else return false;
          }
        }

        newIndex = getNewActiveMatchIndex();

        if(!$select.selected.length || newIndex === false) $selectMultiple.activeMatchIndex = -1;
        else $selectMultiple.activeMatchIndex = Math.min(last,Math.max(first,newIndex));

        return true;
      }

      $select.searchInput.on('keyup', function(e) {

        if ( ! KEY.isVerticalMovement(e.which) ) {
          scope.$evalAsync( function () {
            $select.activeIndex = $select.taggingLabel === false ? -1 : 0;
          });
        }
        // Push a "create new" item into array if there is a search string
        if ( $select.tagging.isActivated && $select.search.length > 0 ) {

          // return early with these keys
          if (e.which === KEY.TAB || KEY.isControl(e) || KEY.isFunctionKey(e) || e.which === KEY.ESC || KEY.isVerticalMovement(e.which) ) {
            return;
          }
          // always reset the activeIndex to the first item when tagging
          $select.activeIndex = $select.taggingLabel === false ? -1 : 0;
          // taggingLabel === false bypasses all of this
          if ($select.taggingLabel === false) return;

          var items = angular.copy( $select.items );
          var stashArr = angular.copy( $select.items );
          var newItem;
          var item;
          var hasTag = false;
          var dupeIndex = -1;
          var tagItems;
          var tagItem;

          // case for object tagging via transform `$select.tagging.fct` function
          if ( $select.tagging.fct !== undefined) {
            tagItems = $select.$filter('filter')(items,{'isTag': true});
            if ( tagItems.length > 0 ) {
              tagItem = tagItems[0];
            }
            // remove the first element, if it has the `isTag` prop we generate a new one with each keyup, shaving the previous
            if ( items.length > 0 && tagItem ) {
              hasTag = true;
              items = items.slice(1,items.length);
              stashArr = stashArr.slice(1,stashArr.length);
            }
            newItem = $select.tagging.fct($select.search);
            // verify the new tag doesn't match the value of a possible selection choice or an already selected item.
            if (
              stashArr.some(function (origItem) {
                 return angular.equals(origItem, newItem);
              }) ||
              $select.selected.some(function (origItem) {
                return angular.equals(origItem, newItem);
              })
            ) {
              scope.$evalAsync(function () {
                $select.activeIndex = 0;
                $select.items = items;
              });
              return;
            }
            if (newItem) newItem.isTag = true;
          // handle newItem string and stripping dupes in tagging string context
          } else {
            // find any tagging items already in the $select.items array and store them
            tagItems = $select.$filter('filter')(items,function (item) {
              return item.match($select.taggingLabel);
            });
            if ( tagItems.length > 0 ) {
              tagItem = tagItems[0];
            }
            item = items[0];
            // remove existing tag item if found (should only ever be one tag item)
            if ( item !== undefined && items.length > 0 && tagItem ) {
              hasTag = true;
              items = items.slice(1,items.length);
              stashArr = stashArr.slice(1,stashArr.length);
            }
            newItem = $select.search+' '+$select.taggingLabel;
            if ( _findApproxDupe($select.selected, $select.search) > -1 ) {
              return;
            }
            // verify the the tag doesn't match the value of an existing item from
            // the searched data set or the items already selected
            if ( _findCaseInsensitiveDupe(stashArr.concat($select.selected)) ) {
              // if there is a tag from prev iteration, strip it / queue the change
              // and return early
              if ( hasTag ) {
                items = stashArr;
                scope.$evalAsync( function () {
                  $select.activeIndex = 0;
                  $select.items = items;
                });
              }
              return;
            }
            if ( _findCaseInsensitiveDupe(stashArr) ) {
              // if there is a tag from prev iteration, strip it
              if ( hasTag ) {
                $select.items = stashArr.slice(1,stashArr.length);
              }
              return;
            }
          }
          if ( hasTag ) dupeIndex = _findApproxDupe($select.selected, newItem);
          // dupe found, shave the first item
          if ( dupeIndex > -1 ) {
            items = items.slice(dupeIndex+1,items.length-1);
          } else {
            items = [];
            if (newItem) items.push(newItem);
            items = items.concat(stashArr);
          }
          scope.$evalAsync( function () {
            $select.activeIndex = 0;
            $select.items = items;

            if ($select.isGrouped) {
              // update item references in groups, so that indexOf will work after angular.copy
              var itemsWithoutTag = newItem ? items.slice(1) : items;
              $select.setItemsFn(itemsWithoutTag);
              if (newItem) {
                // add tag item as a new group
                $select.items.unshift(newItem);
                $select.groups.unshift({name: '', items: [newItem], tagging: true});
              }
            }
          });
        }
      });
      function _findCaseInsensitiveDupe(arr) {
        if ( arr === undefined || $select.search === undefined ) {
          return false;
        }
        var hasDupe = arr.filter( function (origItem) {
          if ( $select.search.toUpperCase() === undefined || origItem === undefined ) {
            return false;
          }
          return origItem.toUpperCase() === $select.search.toUpperCase();
        }).length > 0;

        return hasDupe;
      }
      function _findApproxDupe(haystack, needle) {
        var dupeIndex = -1;
        if(angular.isArray(haystack)) {
          var tempArr = angular.copy(haystack);
          for (var i = 0; i <tempArr.length; i++) {
            // handle the simple string version of tagging
            if ( $select.tagging.fct === undefined ) {
              // search the array for the match
              if ( tempArr[i]+' '+$select.taggingLabel === needle ) {
              dupeIndex = i;
              }
            // handle the object tagging implementation
            } else {
              var mockObj = tempArr[i];
              if (angular.isObject(mockObj)) {
                mockObj.isTag = true;
              }
              if ( angular.equals(mockObj, needle) ) {
                dupeIndex = i;
              }
            }
          }
        }
        return dupeIndex;
      }

      $select.searchInput.on('blur', function() {
        $timeout(function() {
          $selectMultiple.activeMatchIndex = -1;
        });
      });

    }
  };
}]);

uis.directive('uiSelectNoChoice',
    ['uiSelectConfig', function (uiSelectConfig) {
        return {
            restrict: 'EA',
            require: '^uiSelect',
            replace: true,
            transclude: true,
            templateUrl: function (tElement) {
                // Needed so the uiSelect can detect the transcluded content
                tElement.addClass('ui-select-no-choice');

                // Gets theme attribute from parent (ui-select)
                var theme = tElement.parent().attr('theme') || uiSelectConfig.theme;
                return theme + '/no-choice.tpl.html';
            }
        };
    }]);

uis.directive('uiSelectSingle', ['$timeout','$compile', function($timeout, $compile) {
  return {
    restrict: 'EA',
    require: ['^uiSelect', '^ngModel'],
    link: function(scope, element, attrs, ctrls) {

      var $select = ctrls[0];
      var ngModel = ctrls[1];

      //From view --> model
      ngModel.$parsers.unshift(function (inputValue) {
        // Keep original value for undefined and null
        if (isNil(inputValue)) {
          return inputValue;
        }

        var locals = {},
            result;
        locals[$select.parserResult.itemName] = inputValue;
        result = $select.parserResult.modelMapper(scope, locals);
        return result;
      });

      //From model --> view
      ngModel.$formatters.unshift(function (inputValue) {
        // Keep original value for undefined and null
        if (isNil(inputValue)) {
          return inputValue;
        }

        var data = $select.parserResult && $select.parserResult.source (scope, { $select : {search:''}}), //Overwrite $search
            locals = {},
            result;
        if (data){
          var checkFnSingle = function(d){
            locals[$select.parserResult.itemName] = d;
            result = $select.parserResult.modelMapper(scope, locals);
            return result === inputValue;
          };
          //If possible pass same object stored in $select.selected
          if ($select.selected && checkFnSingle($select.selected)) {
            return $select.selected;
          }
          for (var i = data.length - 1; i >= 0; i--) {
            if (checkFnSingle(data[i])) return data[i];
          }
        }
        return inputValue;
      });

      //Update viewValue if model change
      scope.$watch('$select.selected', function(newValue) {
        if (ngModel.$viewValue !== newValue) {
          ngModel.$setViewValue(newValue);
        }
      });

      ngModel.$render = function() {
        $select.selected = ngModel.$viewValue;
      };

      scope.$on('uis:select', function (event, item) {
        $select.selected = item;
        var locals = {};
        locals[$select.parserResult.itemName] = item;

        $timeout(function() {
          $select.onSelectCallback(scope, {
            $item: item,
            $model: isNil(item) ? item : $select.parserResult.modelMapper(scope, locals)
          });
        });
      });

      scope.$on('uis:close', function (event, skipFocusser) {
        $timeout(function(){
          $select.focusser.prop('disabled', false);
          if (!skipFocusser) $select.focusser[0].focus();
        },0,false);
      });

      scope.$on('uis:activate', function () {
        focusser.prop('disabled', true); //Will reactivate it on .close()
      });

      //Idea from: https://github.com/ivaynberg/select2/blob/79b5bf6db918d7560bdd959109b7bcfb47edaf43/select2.js#L1954
      var focusser = angular.element("<input ng-disabled='$select.disabled' class='ui-select-focusser ui-select-offscreen' type='text' id='{{ $select.focusserId }}' aria-label='{{ $select.focusserTitle }}' aria-haspopup='true' role='button' />");
      $compile(focusser)(scope);
      $select.focusser = focusser;

      //Input that will handle focus
      $select.focusInput = focusser;

      element.parent().append(focusser);
      focusser.bind("focus", function(){
        scope.$evalAsync(function(){
          $select.focus = true;
        });
      });
      focusser.bind("blur", function(){
        scope.$evalAsync(function(){
          $select.focus = false;
        });
      });
      focusser.bind("keydown", function(e){

        if (e.which === KEY.BACKSPACE && $select.backspaceReset !== false) {
          e.preventDefault();
          e.stopPropagation();
          $select.select(undefined);
          scope.$apply();
          return;
        }

        if (e.which === KEY.TAB || KEY.isControl(e) || KEY.isFunctionKey(e) || e.which === KEY.ESC) {
          return;
        }

        if (e.which == KEY.DOWN  || e.which == KEY.UP || e.which == KEY.ENTER || e.which == KEY.SPACE){
          e.preventDefault();
          e.stopPropagation();
          $select.activate();
        }

        scope.$digest();
      });

      focusser.bind("keyup input", function(e){

        if (e.which === KEY.TAB || KEY.isControl(e) || KEY.isFunctionKey(e) || e.which === KEY.ESC || e.which == KEY.ENTER || e.which === KEY.BACKSPACE) {
          return;
        }

        $select.activate(focusser.val()); //User pressed some regular key, so we pass it to the search input
        focusser.val('');
        scope.$digest();

      });


    }
  };
}]);

// Make multiple matches sortable
uis.directive('uiSelectSort', ['$timeout', 'uiSelectConfig', 'uiSelectMinErr', function($timeout, uiSelectConfig, uiSelectMinErr) {
  return {
    require: ['^^uiSelect', '^ngModel'],
    link: function(scope, element, attrs, ctrls) {
      if (scope[attrs.uiSelectSort] === null) {
        throw uiSelectMinErr('sort', 'Expected a list to sort');
      }

      var $select = ctrls[0];
      var $ngModel = ctrls[1];

      var options = angular.extend({
          axis: 'horizontal'
        },
        scope.$eval(attrs.uiSelectSortOptions));

      var axis = options.axis;
      var draggingClassName = 'dragging';
      var droppingClassName = 'dropping';
      var droppingBeforeClassName = 'dropping-before';
      var droppingAfterClassName = 'dropping-after';

      scope.$watch(function(){
        return $select.sortable;
      }, function(newValue){
        if (newValue) {
          element.attr('draggable', true);
        } else {
          element.removeAttr('draggable');
        }
      });

      element.on('dragstart', function(event) {
        element.addClass(draggingClassName);

        (event.dataTransfer || event.originalEvent.dataTransfer).setData('text', scope.$index.toString());
      });

      element.on('dragend', function() {
        removeClass(draggingClassName);
      });

      var move = function(from, to) {
        /*jshint validthis: true */
        this.splice(to, 0, this.splice(from, 1)[0]);
      };

      var removeClass = function(className) {
        angular.forEach($select.$element.querySelectorAll('.' + className), function(el){
          angular.element(el).removeClass(className);
        });
      };

      var dragOverHandler = function(event) {
        event.preventDefault();

        var offset = axis === 'vertical' ? event.offsetY || event.layerY || (event.originalEvent ? event.originalEvent.offsetY : 0) : event.offsetX || event.layerX || (event.originalEvent ? event.originalEvent.offsetX : 0);

        if (offset < (this[axis === 'vertical' ? 'offsetHeight' : 'offsetWidth'] / 2)) {
          removeClass(droppingAfterClassName);
          element.addClass(droppingBeforeClassName);

        } else {
          removeClass(droppingBeforeClassName);
          element.addClass(droppingAfterClassName);
        }
      };

      var dropTimeout;

      var dropHandler = function(event) {
        event.preventDefault();

        var droppedItemIndex = parseInt((event.dataTransfer || event.originalEvent.dataTransfer).getData('text'), 10);

        // prevent event firing multiple times in firefox
        $timeout.cancel(dropTimeout);
        dropTimeout = $timeout(function() {
          _dropHandler(droppedItemIndex);
        }, 20);
      };

      var _dropHandler = function(droppedItemIndex) {
        var theList = scope.$eval(attrs.uiSelectSort);
        var itemToMove = theList[droppedItemIndex];
        var newIndex = null;

        if (element.hasClass(droppingBeforeClassName)) {
          if (droppedItemIndex < scope.$index) {
            newIndex = scope.$index - 1;
          } else {
            newIndex = scope.$index;
          }
        } else {
          if (droppedItemIndex < scope.$index) {
            newIndex = scope.$index;
          } else {
            newIndex = scope.$index + 1;
          }
        }

        move.apply(theList, [droppedItemIndex, newIndex]);

        $ngModel.$setViewValue(Date.now());

        scope.$apply(function() {
          scope.$emit('uiSelectSort:change', {
            array: theList,
            item: itemToMove,
            from: droppedItemIndex,
            to: newIndex
          });
        });

        removeClass(droppingClassName);
        removeClass(droppingBeforeClassName);
        removeClass(droppingAfterClassName);

        element.off('drop', dropHandler);
      };

      element.on('dragenter', function() {
        if (element.hasClass(draggingClassName)) {
          return;
        }

        element.addClass(droppingClassName);

        element.on('dragover', dragOverHandler);
        element.on('drop', dropHandler);
      });

      element.on('dragleave', function(event) {
        if (event.target != element) {
          return;
        }

        removeClass(droppingClassName);
        removeClass(droppingBeforeClassName);
        removeClass(droppingAfterClassName);

        element.off('dragover', dragOverHandler);
        element.off('drop', dropHandler);
      });
    }
  };
}]);

/**
 * Debounces functions
 *
 * Taken from UI Bootstrap $$debounce source code
 * See https://github.com/angular-ui/bootstrap/blob/master/src/debounce/debounce.js
 *
 */
uis.factory('$$uisDebounce', ['$timeout', function($timeout) {
  return function(callback, debounceTime) {
    var timeoutPromise;

    return function() {
      var self = this;
      var args = Array.prototype.slice.call(arguments);
      if (timeoutPromise) {
        $timeout.cancel(timeoutPromise);
      }

      timeoutPromise = $timeout(function() {
        callback.apply(self, args);
      }, debounceTime);
    };
  };
}]);

uis.directive('uisOpenClose', ['$parse', '$timeout', function ($parse, $timeout) {
  return {
    restrict: 'A',
    require: 'uiSelect',
    link: function (scope, element, attrs, $select) {
      $select.onOpenCloseCallback = $parse(attrs.uisOpenClose);

      scope.$watch('$select.open', function (isOpen, previousState) {
        if (isOpen !== previousState) {
          $timeout(function () {
            $select.onOpenCloseCallback(scope, {
              isOpen: isOpen
            });
          });
        }
      });
    }
  };
}]);

/**
 * Parses "repeat" attribute.
 *
 * Taken from AngularJS ngRepeat source code
 * See https://github.com/angular/angular.js/blob/v1.2.15/src/ng/directive/ngRepeat.js#L211
 *
 * Original discussion about parsing "repeat" attribute instead of fully relying on ng-repeat:
 * https://github.com/angular-ui/ui-select/commit/5dd63ad#commitcomment-5504697
 */

uis.service('uisRepeatParser', ['uiSelectMinErr','$parse', function(uiSelectMinErr, $parse) {
  var self = this;

  /**
   * Example:
   * expression = "address in addresses | filter: {street: $select.search} track by $index"
   * itemName = "address",
   * source = "addresses | filter: {street: $select.search}",
   * trackByExp = "$index",
   */
  self.parse = function(expression) {


    var match;
    //var isObjectCollection = /\(\s*([\$\w][\$\w]*)\s*,\s*([\$\w][\$\w]*)\s*\)/.test(expression);
    // If an array is used as collection

    // if (isObjectCollection){
    // 000000000000000000000000000000111111111000000000000000222222222222220033333333333333333333330000444444444444444444000000000000000055555555555000000000000000000000066666666600000000
    match = expression.match(/^\s*(?:([\s\S]+?)\s+as\s+)?(?:([\$\w][\$\w]*)|(?:\(\s*([\$\w][\$\w]*)\s*,\s*([\$\w][\$\w]*)\s*\)))\s+in\s+(\s*[\s\S]+?)?(?:\s+track\s+by\s+([\s\S]+?))?\s*$/);

    // 1 Alias
    // 2 Item
    // 3 Key on (key,value)
    // 4 Value on (key,value)
    // 5 Source expression (including filters)
    // 6 Track by

    if (!match) {
      throw uiSelectMinErr('iexp', "Expected expression in form of '_item_ in _collection_[ track by _id_]' but got '{0}'.",
              expression);
    }

    var source = match[5],
        filters = '';

    // When using (key,value) ui-select requires filters to be extracted, since the object
    // is converted to an array for $select.items
    // (in which case the filters need to be reapplied)
    if (match[3]) {
      // Remove any enclosing parenthesis
      source = match[5].replace(/(^\()|(\)$)/g, '');
      // match all after | but not after ||
      var filterMatch = match[5].match(/^\s*(?:[\s\S]+?)(?:[^\|]|\|\|)+([\s\S]*)\s*$/);
      if(filterMatch && filterMatch[1].trim()) {
        filters = filterMatch[1];
        source = source.replace(filters, '');
      }
    }

    return {
      itemName: match[4] || match[2], // (lhs) Left-hand side,
      keyName: match[3], //for (key, value) syntax
      source: $parse(source),
      filters: filters,
      trackByExp: match[6],
      modelMapper: $parse(match[1] || match[4] || match[2]),
      repeatExpression: function (grouped) {
        var expression = this.itemName + ' in ' + (grouped ? '$group.items' : '$select.items');
        if (this.trackByExp) {
          expression += ' track by ' + this.trackByExp;
        }
        return expression;
      }
    };

  };

  self.getGroupNgRepeatExpression = function() {
    return '$group in $select.groups track by $group.name';
  };

}]);

}());
angular.module("ui.select").run(["$templateCache", function($templateCache) {$templateCache.put("bootstrap/choices.tpl.html","<ul class=\"ui-select-choices ui-select-choices-content ui-select-dropdown dropdown-menu\" ng-show=\"$select.open && $select.items.length > 0\"><li class=\"ui-select-choices-group\" id=\"ui-select-choices-{{ $select.generatedId }}\"><div class=\"divider\" ng-show=\"$select.isGrouped && $index > 0\"></div><div ng-show=\"$select.isGrouped\" class=\"ui-select-choices-group-label dropdown-header\" ng-bind=\"$group.name\"></div><div ng-attr-id=\"ui-select-choices-row-{{ $select.generatedId }}-{{$index}}\" class=\"ui-select-choices-row\" ng-class=\"{active: $select.isActive(this), disabled: $select.isDisabled(this)}\" role=\"option\"><span class=\"ui-select-choices-row-inner\"></span></div></li></ul>");
$templateCache.put("bootstrap/match-multiple.tpl.html","<span class=\"ui-select-match\"><span ng-repeat=\"$item in $select.selected track by $index\"><span class=\"ui-select-match-item btn btn-default btn-xs\" tabindex=\"-1\" type=\"button\" ng-disabled=\"$select.disabled\" ng-click=\"$selectMultiple.activeMatchIndex = $index;\" ng-class=\"{\'btn-primary\':$selectMultiple.activeMatchIndex === $index, \'select-locked\':$select.isLocked(this, $index)}\" ui-select-sort=\"$select.selected\"><span class=\"close ui-select-match-close\" ng-hide=\"$select.disabled\" ng-click=\"$selectMultiple.removeChoice($index)\">&nbsp;&times;</span> <span uis-transclude-append=\"\"></span></span></span></span>");
$templateCache.put("bootstrap/match.tpl.html","<div class=\"ui-select-match\" ng-hide=\"$select.open && $select.searchEnabled\" ng-disabled=\"$select.disabled\" ng-class=\"{\'btn-default-focus\':$select.focus}\"><span tabindex=\"-1\" class=\"btn btn-default form-control ui-select-toggle\" aria-label=\"{{ $select.baseTitle }} activate\" ng-disabled=\"$select.disabled\" ng-click=\"$select.activate()\" style=\"outline: 0;\"><span ng-show=\"$select.isEmpty()\" class=\"ui-select-placeholder text-muted\">{{$select.placeholder}}</span> <span ng-hide=\"$select.isEmpty()\" class=\"ui-select-match-text pull-left\" ng-class=\"{\'ui-select-allow-clear\': $select.allowClear && !$select.isEmpty()}\" ng-transclude=\"\"></span> <i class=\"caret pull-right\" ng-click=\"$select.toggle($event)\"></i> <a ng-show=\"$select.allowClear && !$select.isEmpty() && ($select.disabled !== true)\" aria-label=\"{{ $select.baseTitle }} clear\" style=\"margin-right: 10px\" ng-click=\"$select.clear($event)\" class=\"btn btn-xs btn-link pull-right\"><i class=\"glyphicon glyphicon-remove\" aria-hidden=\"true\"></i></a></span></div>");
$templateCache.put("bootstrap/no-choice.tpl.html","<ul class=\"ui-select-no-choice dropdown-menu\" ng-show=\"$select.items.length == 0\"><li ng-transclude=\"\"></li></ul>");
$templateCache.put("bootstrap/select-multiple.tpl.html","<div class=\"ui-select-container ui-select-multiple ui-select-bootstrap dropdown form-control\" ng-class=\"{open: $select.open}\"><div><div class=\"ui-select-match\"></div><input type=\"search\" autocomplete=\"off\" autocorrect=\"off\" autocapitalize=\"off\" spellcheck=\"false\" class=\"ui-select-search input-xs\" placeholder=\"{{$selectMultiple.getPlaceholder()}}\" ng-disabled=\"$select.disabled\" ng-click=\"$select.activate()\" ng-model=\"$select.search\" role=\"combobox\" aria-expanded=\"{{$select.open}}\" aria-label=\"{{$select.baseTitle}}\" ng-class=\"{\'spinner\': $select.refreshing}\" ondrop=\"return false;\"></div><div class=\"ui-select-choices\"></div><div class=\"ui-select-no-choice\"></div></div>");
$templateCache.put("bootstrap/select.tpl.html","<div class=\"ui-select-container ui-select-bootstrap dropdown\" ng-class=\"{open: $select.open}\"><div class=\"ui-select-match\"></div><span ng-show=\"$select.open && $select.refreshing && $select.spinnerEnabled\" class=\"ui-select-refreshing {{$select.spinnerClass}}\"></span> <input type=\"search\" autocomplete=\"off\" tabindex=\"-1\" aria-expanded=\"true\" aria-label=\"{{ $select.baseTitle }}\" aria-owns=\"ui-select-choices-{{ $select.generatedId }}\" class=\"form-control ui-select-search\" ng-class=\"{ \'ui-select-search-hidden\' : !$select.searchEnabled }\" placeholder=\"{{$select.placeholder}}\" ng-model=\"$select.search\" ng-show=\"$select.open\"><div class=\"ui-select-choices\"></div><div class=\"ui-select-no-choice\"></div></div>");
$templateCache.put("select2/choices.tpl.html","<ul tabindex=\"-1\" class=\"ui-select-choices ui-select-choices-content select2-results\"><li class=\"ui-select-choices-group\" ng-class=\"{\'select2-result-with-children\': $select.choiceGrouped($group) }\"><div ng-show=\"$select.choiceGrouped($group)\" class=\"ui-select-choices-group-label select2-result-label\" ng-bind=\"$group.name\"></div><ul id=\"ui-select-choices-{{ $select.generatedId }}\" ng-class=\"{\'select2-result-sub\': $select.choiceGrouped($group), \'select2-result-single\': !$select.choiceGrouped($group) }\"><li role=\"option\" ng-attr-id=\"ui-select-choices-row-{{ $select.generatedId }}-{{$index}}\" class=\"ui-select-choices-row\" ng-class=\"{\'select2-highlighted\': $select.isActive(this), \'select2-disabled\': $select.isDisabled(this)}\"><div class=\"select2-result-label ui-select-choices-row-inner\"></div></li></ul></li></ul>");
$templateCache.put("select2/match-multiple.tpl.html","<span class=\"ui-select-match\"><li class=\"ui-select-match-item select2-search-choice\" ng-repeat=\"$item in $select.selected track by $index\" ng-class=\"{\'select2-search-choice-focus\':$selectMultiple.activeMatchIndex === $index, \'select2-locked\':$select.isLocked(this, $index)}\" ui-select-sort=\"$select.selected\"><span uis-transclude-append=\"\"></span> <a href=\"javascript:;\" class=\"ui-select-match-close select2-search-choice-close\" ng-click=\"$selectMultiple.removeChoice($index)\" tabindex=\"-1\"></a></li></span>");
$templateCache.put("select2/match.tpl.html","<a class=\"select2-choice ui-select-match\" ng-class=\"{\'select2-default\': $select.isEmpty()}\" ng-click=\"$select.toggle($event)\" aria-label=\"{{ $select.baseTitle }} select\"><span ng-show=\"$select.isEmpty()\" class=\"select2-chosen\">{{$select.placeholder}}</span> <span ng-hide=\"$select.isEmpty()\" class=\"select2-chosen\" ng-transclude=\"\"></span> <abbr ng-if=\"$select.allowClear && !$select.isEmpty()\" class=\"select2-search-choice-close\" ng-click=\"$select.clear($event)\"></abbr> <span class=\"select2-arrow ui-select-toggle\"><b></b></span></a>");
$templateCache.put("select2/no-choice.tpl.html","<div class=\"ui-select-no-choice dropdown\" ng-show=\"$select.items.length == 0\"><div class=\"dropdown-content\"><div data-selectable=\"\" ng-transclude=\"\"></div></div></div>");
$templateCache.put("select2/select-multiple.tpl.html","<div class=\"ui-select-container ui-select-multiple select2 select2-container select2-container-multi\" ng-class=\"{\'select2-container-active select2-dropdown-open open\': $select.open, \'select2-container-disabled\': $select.disabled}\"><ul class=\"select2-choices\"><span class=\"ui-select-match\"></span><li class=\"select2-search-field\"><input type=\"search\" autocomplete=\"off\" autocorrect=\"off\" autocapitalize=\"off\" spellcheck=\"false\" role=\"combobox\" aria-expanded=\"true\" aria-owns=\"ui-select-choices-{{ $select.generatedId }}\" aria-label=\"{{ $select.baseTitle }}\" aria-activedescendant=\"ui-select-choices-row-{{ $select.generatedId }}-{{ $select.activeIndex }}\" class=\"select2-input ui-select-search\" placeholder=\"{{$selectMultiple.getPlaceholder()}}\" ng-disabled=\"$select.disabled\" ng-hide=\"$select.disabled\" ng-model=\"$select.search\" ng-click=\"$select.activate()\" style=\"width: 34px;\" ondrop=\"return false;\"></li></ul><div class=\"ui-select-dropdown select2-drop select2-with-searchbox select2-drop-active\" ng-class=\"{\'select2-display-none\': !$select.open || $select.items.length === 0}\"><div class=\"ui-select-choices\"></div></div></div>");
$templateCache.put("select2/select.tpl.html","<div class=\"ui-select-container select2 select2-container\" ng-class=\"{\'select2-container-active select2-dropdown-open open\': $select.open, \'select2-container-disabled\': $select.disabled, \'select2-container-active\': $select.focus, \'select2-allowclear\': $select.allowClear && !$select.isEmpty()}\"><div class=\"ui-select-match\"></div><div class=\"ui-select-dropdown select2-drop select2-with-searchbox select2-drop-active\" ng-class=\"{\'select2-display-none\': !$select.open}\"><div class=\"search-container\" ng-class=\"{\'ui-select-search-hidden\':!$select.searchEnabled, \'select2-search\':$select.searchEnabled}\"><input type=\"search\" autocomplete=\"off\" autocorrect=\"off\" autocapitalize=\"off\" spellcheck=\"false\" ng-class=\"{\'select2-active\': $select.refreshing}\" role=\"combobox\" aria-expanded=\"true\" aria-owns=\"ui-select-choices-{{ $select.generatedId }}\" aria-label=\"{{ $select.baseTitle }}\" class=\"ui-select-search select2-input\" ng-model=\"$select.search\"></div><div class=\"ui-select-choices\"></div><div class=\"ui-select-no-choice\"></div></div></div>");
$templateCache.put("selectize/choices.tpl.html","<div ng-show=\"$select.open\" class=\"ui-select-choices ui-select-dropdown selectize-dropdown\" ng-class=\"{\'single\': !$select.multiple, \'multi\': $select.multiple}\"><div class=\"ui-select-choices-content selectize-dropdown-content\"><div class=\"ui-select-choices-group optgroup\"><div ng-show=\"$select.isGrouped\" class=\"ui-select-choices-group-label optgroup-header\" ng-bind=\"$group.name\"></div><div role=\"option\" class=\"ui-select-choices-row\" ng-class=\"{active: $select.isActive(this), disabled: $select.isDisabled(this)}\"><div class=\"option ui-select-choices-row-inner\" data-selectable=\"\"></div></div></div></div></div>");
$templateCache.put("selectize/match-multiple.tpl.html","<div class=\"ui-select-match\" data-value=\"\" ng-repeat=\"$item in $select.selected track by $index\" ng-click=\"$selectMultiple.activeMatchIndex = $index;\" ng-class=\"{\'active\':$selectMultiple.activeMatchIndex === $index}\" ui-select-sort=\"$select.selected\"><span class=\"ui-select-match-item\" ng-class=\"{\'select-locked\':$select.isLocked(this, $index)}\"><span uis-transclude-append=\"\"></span> <span class=\"remove ui-select-match-close\" ng-hide=\"$select.disabled\" ng-click=\"$selectMultiple.removeChoice($index)\">&times;</span></span></div>");
$templateCache.put("selectize/match.tpl.html","<div ng-hide=\"$select.searchEnabled && ($select.open || $select.isEmpty())\" class=\"ui-select-match\"><span ng-show=\"!$select.searchEnabled && ($select.isEmpty() || $select.open)\" class=\"ui-select-placeholder text-muted\">{{$select.placeholder}}</span> <span ng-hide=\"$select.isEmpty() || $select.open\" ng-transclude=\"\"></span></div>");
$templateCache.put("selectize/no-choice.tpl.html","<div class=\"ui-select-no-choice selectize-dropdown\" ng-show=\"$select.items.length == 0\"><div class=\"selectize-dropdown-content\"><div data-selectable=\"\" ng-transclude=\"\"></div></div></div>");
$templateCache.put("selectize/select-multiple.tpl.html","<div class=\"ui-select-container selectize-control multi plugin-remove_button\" ng-class=\"{\'open\': $select.open}\"><div class=\"selectize-input\" ng-class=\"{\'focus\': $select.open, \'disabled\': $select.disabled, \'selectize-focus\' : $select.focus}\" ng-click=\"$select.open && !$select.searchEnabled ? $select.toggle($event) : $select.activate()\"><div class=\"ui-select-match\"></div><input type=\"search\" autocomplete=\"off\" tabindex=\"-1\" class=\"ui-select-search\" ng-class=\"{\'ui-select-search-hidden\':!$select.searchEnabled}\" placeholder=\"{{$selectMultiple.getPlaceholder()}}\" ng-model=\"$select.search\" ng-disabled=\"$select.disabled\" aria-expanded=\"{{$select.open}}\" aria-label=\"{{ $select.baseTitle }}\" ondrop=\"return false;\"></div><div class=\"ui-select-choices\"></div><div class=\"ui-select-no-choice\"></div></div>");
$templateCache.put("selectize/select.tpl.html","<div class=\"ui-select-container selectize-control single\" ng-class=\"{\'open\': $select.open}\"><div class=\"selectize-input\" ng-class=\"{\'focus\': $select.open, \'disabled\': $select.disabled, \'selectize-focus\' : $select.focus}\" ng-click=\"$select.open && !$select.searchEnabled ? $select.toggle($event) : $select.activate()\"><div class=\"ui-select-match\"></div><input type=\"search\" autocomplete=\"off\" tabindex=\"-1\" class=\"ui-select-search ui-select-toggle\" ng-class=\"{\'ui-select-search-hidden\':!$select.searchEnabled}\" ng-click=\"$select.toggle($event)\" placeholder=\"{{$select.placeholder}}\" ng-model=\"$select.search\" ng-hide=\"!$select.isEmpty() && !$select.open\" ng-disabled=\"$select.disabled\" aria-label=\"{{ $select.baseTitle }}\"></div><div class=\"ui-select-choices\"></div><div class=\"ui-select-no-choice\"></div></div>");}]);



/**
 * dirPagination - AngularJS module for paginating (almost) anything (especially ng-repeat).
 *
 *
 * Credits
 * =======
 *
 * Daniel Tabuenca: https://groups.google.com/d/msg/angular/an9QpzqIYiM/r8v-3W1X5vcJ
 * for the idea on how to dynamically invoke the ng-repeat directive.
 *
 * I borrowed a couple of lines and a few attribute names from the AngularUI Bootstrap project:
 * https://github.com/angular-ui/bootstrap/blob/master/src/pagination/pagination.js
 *
 * Copyright 2014 Michael Bromley <michael@michaelbromley.co.uk>
 */

(function() {

    /**
     * Config
     */
    var moduleName = 'angularUtils.directives.dirPagination';
    var DEFAULT_ID = '__default';

    /**
     * Module
     */
    angular.module(moduleName, [])
        .directive('dirPaginate', ['$compile', '$parse', 'paginationService', dirPaginateDirective])
        .directive('dirPaginateNoCompile', noCompileDirective)
        .directive('dirPaginationControls', ['paginationService', 'paginationTemplate', dirPaginationControlsDirective])
        .filter('itemsPerPage', ['paginationService', itemsPerPageFilter])
        .service('paginationService', paginationService)
        .provider('paginationTemplate', paginationTemplateProvider)
        .run(['$templateCache',dirPaginationControlsTemplateInstaller]);

    function dirPaginateDirective($compile, $parse, paginationService) {

        return  {
            terminal: true,
            multiElement: true,
            priority: 100,
            compile: dirPaginationCompileFn
        };

        function dirPaginationCompileFn(tElement, tAttrs){

            var expression = tAttrs.dirPaginate;
            // regex taken directly from https://github.com/angular/angular.js/blob/v1.4.x/src/ng/directive/ngRepeat.js#L339
            var match = expression.match(/^\s*([\s\S]+?)\s+in\s+([\s\S]+?)(?:\s+as\s+([\s\S]+?))?(?:\s+track\s+by\s+([\s\S]+?))?\s*$/);

            var filterPattern = /\|\s*itemsPerPage\s*:\s*(.*\(\s*\w*\)|([^\)]*?(?=\s+as\s+))|[^\)]*)/;
            if (match[2].match(filterPattern) === null) {
                throw 'pagination directive: the \'itemsPerPage\' filter must be set.';
            }
            var itemsPerPageFilterRemoved = match[2].replace(filterPattern, '');
            var collectionGetter = $parse(itemsPerPageFilterRemoved);

            addNoCompileAttributes(tElement);

            // If any value is specified for paginationId, we register the un-evaluated expression at this stage for the benefit of any
            // dir-pagination-controls directives that may be looking for this ID.
            var rawId = tAttrs.paginationId || DEFAULT_ID;
            paginationService.registerInstance(rawId);

            return function dirPaginationLinkFn(scope, element, attrs){

                // Now that we have access to the `scope` we can interpolate any expression given in the paginationId attribute and
                // potentially register a new ID if it evaluates to a different value than the rawId.
                var paginationId = $parse(attrs.paginationId)(scope) || attrs.paginationId || DEFAULT_ID;
                // In case rawId != paginationId we deregister using rawId for the sake of general cleanliness
                // before registering using paginationId
                paginationService.deregisterInstance(rawId);
                paginationService.registerInstance(paginationId);

                var repeatExpression = getRepeatExpression(expression, paginationId);
                addNgRepeatToElement(element, attrs, repeatExpression);

                removeTemporaryAttributes(element);
                var compiled =  $compile(element);

                var currentPageGetter = makeCurrentPageGetterFn(scope, attrs, paginationId);
                paginationService.setCurrentPageParser(paginationId, currentPageGetter, scope);

                if (typeof attrs.totalItems !== 'undefined') {
                    paginationService.setAsyncModeTrue(paginationId);
                    scope.$watch(function() {
                        return $parse(attrs.totalItems)(scope);
                    }, function (result) {
                        if (0 <= result) {
                            paginationService.setCollectionLength(paginationId, result);
                        }
                    });
                } else {
                    paginationService.setAsyncModeFalse(paginationId);
                    scope.$watchCollection(function() {
                        return collectionGetter(scope);
                    }, function(collection) {
                        if (collection) {
                            var collectionLength = (collection instanceof Array) ? collection.length : Object.keys(collection).length;
                            paginationService.setCollectionLength(paginationId, collectionLength);
                        }
                    });
                }

                // Delegate to the link function returned by the new compilation of the ng-repeat
                compiled(scope);

                // When the scope is destroyed, we make sure to remove the reference to it in paginationService
                // so that it can be properly garbage collected
                scope.$on('$destroy', function destroyDirPagination() {
                    paginationService.deregisterInstance(paginationId);
                });
            };
        }

        /**
         * If a pagination id has been specified, we need to check that it is present as the second argument passed to
         * the itemsPerPage filter. If it is not there, we add it and return the modified expression.
         *
         * @param expression
         * @param paginationId
         * @returns {*}
         */
        function getRepeatExpression(expression, paginationId) {
            var repeatExpression,
                idDefinedInFilter = !!expression.match(/(\|\s*itemsPerPage\s*:[^|]*:[^|]*)/);

            if (paginationId !== DEFAULT_ID && !idDefinedInFilter) {
                repeatExpression = expression.replace(/(\|\s*itemsPerPage\s*:\s*[^|\s]*)/, "$1 : '" + paginationId + "'");
            } else {
                repeatExpression = expression;
            }

            return repeatExpression;
        }

        /**
         * Adds the ng-repeat directive to the element. In the case of multi-element (-start, -end) it adds the
         * appropriate multi-element ng-repeat to the first and last element in the range.
         * @param element
         * @param attrs
         * @param repeatExpression
         */
        function addNgRepeatToElement(element, attrs, repeatExpression) {
            if (element[0].hasAttribute('dir-paginate-start') || element[0].hasAttribute('data-dir-paginate-start')) {
                // using multiElement mode (dir-paginate-start, dir-paginate-end)
                attrs.$set('ngRepeatStart', repeatExpression);
                element.eq(element.length - 1).attr('ng-repeat-end', true);
            } else {
                attrs.$set('ngRepeat', repeatExpression);
            }
        }

        /**
         * Adds the dir-paginate-no-compile directive to each element in the tElement range.
         * @param tElement
         */
        function addNoCompileAttributes(tElement) {
            angular.forEach(tElement, function(el) {
                if (el.nodeType === 1) {
                    angular.element(el).attr('dir-paginate-no-compile', true);
                }
            });
        }

        /**
         * Removes the variations on dir-paginate (data-, -start, -end) and the dir-paginate-no-compile directives.
         * @param element
         */
        function removeTemporaryAttributes(element) {
            angular.forEach(element, function(el) {
                if (el.nodeType === 1) {
                    angular.element(el).removeAttr('dir-paginate-no-compile');
                }
            });
            element.eq(0).removeAttr('dir-paginate-start').removeAttr('dir-paginate').removeAttr('data-dir-paginate-start').removeAttr('data-dir-paginate');
            element.eq(element.length - 1).removeAttr('dir-paginate-end').removeAttr('data-dir-paginate-end');
        }

        /**
         * Creates a getter function for the current-page attribute, using the expression provided or a default value if
         * no current-page expression was specified.
         *
         * @param scope
         * @param attrs
         * @param paginationId
         * @returns {*}
         */
        function makeCurrentPageGetterFn(scope, attrs, paginationId) {
            var currentPageGetter;
            if (attrs.currentPage) {
                currentPageGetter = $parse(attrs.currentPage);
            } else {
                // If the current-page attribute was not set, we'll make our own.
                // Replace any non-alphanumeric characters which might confuse
                // the $parse service and give unexpected results.
                // See https://github.com/michaelbromley/angularUtils/issues/233
                var defaultCurrentPage = (paginationId + '__currentPage').replace(/\W/g, '_');
                scope[defaultCurrentPage] = 1;
                currentPageGetter = $parse(defaultCurrentPage);
            }
            return currentPageGetter;
        }
    }

    /**
     * This is a helper directive that allows correct compilation when in multi-element mode (ie dir-paginate-start, dir-paginate-end).
     * It is dynamically added to all elements in the dir-paginate compile function, and it prevents further compilation of
     * any inner directives. It is then removed in the link function, and all inner directives are then manually compiled.
     */
    function noCompileDirective() {
        return {
            priority: 5000,
            terminal: true
        };
    }

    function dirPaginationControlsTemplateInstaller($templateCache) {
        $templateCache.put('angularUtils.directives.dirPagination.template', '<ul class="pagination" ng-if="1 < pages.length || !autoHide"><li ng-if="boundaryLinks" ng-class="{ disabled : pagination.current == 1 }"><a href="" ng-click="setCurrent(1)">&laquo;</a></li><li ng-if="directionLinks" ng-class="{ disabled : pagination.current == 1 }"><a href="" ng-click="setCurrent(pagination.current - 1)">&lsaquo;</a></li><li ng-repeat="pageNumber in pages track by tracker(pageNumber, $index)" ng-class="{ active : pagination.current == pageNumber, disabled : pageNumber == \'...\' || ( ! autoHide && pages.length === 1 ) }"><a href="" ng-click="setCurrent(pageNumber)">{{ pageNumber }}</a></li><li ng-if="directionLinks" ng-class="{ disabled : pagination.current == pagination.last }"><a href="" ng-click="setCurrent(pagination.current + 1)">&rsaquo;</a></li><li ng-if="boundaryLinks"  ng-class="{ disabled : pagination.current == pagination.last }"><a href="" ng-click="setCurrent(pagination.last)">&raquo;</a></li></ul>');
    }

    function dirPaginationControlsDirective(paginationService, paginationTemplate) {

        var numberRegex = /^\d+$/;

        var DDO = {
            restrict: 'AE',
            scope: {
                maxSize: '=?',
                onPageChange: '&?',
                paginationId: '=?',
                autoHide: '=?'
            },
            link: dirPaginationControlsLinkFn
        };

        // We need to check the paginationTemplate service to see whether a template path or
        // string has been specified, and add the `template` or `templateUrl` property to
        // the DDO as appropriate. The order of priority to decide which template to use is
        // (highest priority first):
        // 1. paginationTemplate.getString()
        // 2. attrs.templateUrl
        // 3. paginationTemplate.getPath()
        var templateString = paginationTemplate.getString();
        if (templateString !== undefined) {
            DDO.template = templateString;
        } else {
            DDO.templateUrl = function(elem, attrs) {
                return attrs.templateUrl || paginationTemplate.getPath();
            };
        }
        return DDO;

        function dirPaginationControlsLinkFn(scope, element, attrs) {

            // rawId is the un-interpolated value of the pagination-id attribute. This is only important when the corresponding dir-paginate directive has
            // not yet been linked (e.g. if it is inside an ng-if block), and in that case it prevents this controls directive from assuming that there is
            // no corresponding dir-paginate directive and wrongly throwing an exception.
            var rawId = attrs.paginationId ||  DEFAULT_ID;
            var paginationId = scope.paginationId || attrs.paginationId ||  DEFAULT_ID;

            if (!paginationService.isRegistered(paginationId) && !paginationService.isRegistered(rawId)) {
                var idMessage = (paginationId !== DEFAULT_ID) ? ' (id: ' + paginationId + ') ' : ' ';
                if (window.console) {
                    console.warn('Pagination directive: the pagination controls' + idMessage + 'cannot be used without the corresponding pagination directive, which was not found at link time.');
                }
            }

            if (!scope.maxSize) { scope.maxSize = 9; }
            scope.autoHide = scope.autoHide === undefined ? true : scope.autoHide;
            scope.directionLinks = angular.isDefined(attrs.directionLinks) ? scope.$parent.$eval(attrs.directionLinks) : true;
            scope.boundaryLinks = angular.isDefined(attrs.boundaryLinks) ? scope.$parent.$eval(attrs.boundaryLinks) : false;

            var paginationRange = Math.max(scope.maxSize, 5);
            scope.pages = [];
            scope.pagination = {
                last: 1,
                current: 1
            };
            scope.range = {
                lower: 1,
                upper: 1,
                total: 1
            };

            scope.$watch('maxSize', function(val) {
                if (val) {
                    paginationRange = Math.max(scope.maxSize, 5);
                    generatePagination();
                }
            });

            scope.$watch(function() {
                if (paginationService.isRegistered(paginationId)) {
                    return (paginationService.getCollectionLength(paginationId) + 1) * paginationService.getItemsPerPage(paginationId);
                }
            }, function(length) {
                if (0 < length) {
                    generatePagination();
                }
            });

            scope.$watch(function() {
                if (paginationService.isRegistered(paginationId)) {
                    return (paginationService.getItemsPerPage(paginationId));
                }
            }, function(current, previous) {
                if (current != previous && typeof previous !== 'undefined') {
                    goToPage(scope.pagination.current);
                }
            });

            scope.$watch(function() {
                if (paginationService.isRegistered(paginationId)) {
                    return paginationService.getCurrentPage(paginationId);
                }
            }, function(currentPage, previousPage) {
                if (currentPage != previousPage) {
                    goToPage(currentPage);
                }
            });

            scope.setCurrent = function(num) {
                if (paginationService.isRegistered(paginationId) && isValidPageNumber(num)) {
                    num = parseInt(num, 10);
                    paginationService.setCurrentPage(paginationId, num);
                }
            };

            /**
             * Custom "track by" function which allows for duplicate "..." entries on long lists,
             * yet fixes the problem of wrongly-highlighted links which happens when using
             * "track by $index" - see https://github.com/michaelbromley/angularUtils/issues/153
             * @param id
             * @param index
             * @returns {string}
             */
            scope.tracker = function(id, index) {
                return id + '_' + index;
            };

            function goToPage(num) {
                if (paginationService.isRegistered(paginationId) && isValidPageNumber(num)) {
                    var oldPageNumber = scope.pagination.current;

                    scope.pages = generatePagesArray(num, paginationService.getCollectionLength(paginationId), paginationService.getItemsPerPage(paginationId), paginationRange);
                    scope.pagination.current = num;
                    updateRangeValues();

                    // if a callback has been set, then call it with the page number as the first argument
                    // and the previous page number as a second argument
                    if (scope.onPageChange) {
                        scope.onPageChange({
                            newPageNumber : num,
                            oldPageNumber : oldPageNumber
                        });
                    }
                }
            }

            function generatePagination() {
                if (paginationService.isRegistered(paginationId)) {
                    var page = parseInt(paginationService.getCurrentPage(paginationId)) || 1;
                    scope.pages = generatePagesArray(page, paginationService.getCollectionLength(paginationId), paginationService.getItemsPerPage(paginationId), paginationRange);
                    scope.pagination.current = page;
                    scope.pagination.last = scope.pages[scope.pages.length - 1];
                    if (scope.pagination.last < scope.pagination.current) {
                        scope.setCurrent(scope.pagination.last);
                    } else {
                        updateRangeValues();
                    }
                }
            }

            /**
             * This function updates the values (lower, upper, total) of the `scope.range` object, which can be used in the pagination
             * template to display the current page range, e.g. "showing 21 - 40 of 144 results";
             */
            function updateRangeValues() {
                if (paginationService.isRegistered(paginationId)) {
                    var currentPage = paginationService.getCurrentPage(paginationId),
                        itemsPerPage = paginationService.getItemsPerPage(paginationId),
                        totalItems = paginationService.getCollectionLength(paginationId);

                    scope.range.lower = (currentPage - 1) * itemsPerPage + 1;
                    scope.range.upper = Math.min(currentPage * itemsPerPage, totalItems);
                    scope.range.total = totalItems;
                }
            }
            function isValidPageNumber(num) {
                return (numberRegex.test(num) && (0 < num && num <= scope.pagination.last));
            }
        }

        /**
         * Generate an array of page numbers (or the '...' string) which is used in an ng-repeat to generate the
         * links used in pagination
         *
         * @param currentPage
         * @param rowsPerPage
         * @param paginationRange
         * @param collectionLength
         * @returns {Array}
         */
        function generatePagesArray(currentPage, collectionLength, rowsPerPage, paginationRange) {
            var pages = [];
            var totalPages = Math.ceil(collectionLength / rowsPerPage);
            var halfWay = Math.ceil(paginationRange / 2);
            var position;

            if (currentPage <= halfWay) {
                position = 'start';
            } else if (totalPages - halfWay < currentPage) {
                position = 'end';
            } else {
                position = 'middle';
            }

            var ellipsesNeeded = paginationRange < totalPages;
            var i = 1;
            while (i <= totalPages && i <= paginationRange) {
                var pageNumber = calculatePageNumber(i, currentPage, paginationRange, totalPages);

                var openingEllipsesNeeded = (i === 2 && (position === 'middle' || position === 'end'));
                var closingEllipsesNeeded = (i === paginationRange - 1 && (position === 'middle' || position === 'start'));
                if (ellipsesNeeded && (openingEllipsesNeeded || closingEllipsesNeeded)) {
                    //pages.push('...');
                } else {
                    pages.push(pageNumber);
                }
                i ++;
            }
            return pages;
        }

        /**
         * Given the position in the sequence of pagination links [i], figure out what page number corresponds to that position.
         *
         * @param i
         * @param currentPage
         * @param paginationRange
         * @param totalPages
         * @returns {*}
         */
        function calculatePageNumber(i, currentPage, paginationRange, totalPages) {
            var halfWay = Math.ceil(paginationRange/2);
            if (i === paginationRange) {
                return totalPages;
            } else if (i === 1) {
                return i;
            } else if (paginationRange < totalPages) {
                if (totalPages - halfWay < currentPage) {
                    return totalPages - paginationRange + i;
                } else if (halfWay < currentPage) {
                    return currentPage - halfWay + i;
                } else {
                    return i;
                }
            } else {
                return i;
            }
        }
    }

    /**
     * This filter slices the collection into pages based on the current page number and number of items per page.
     * @param paginationService
     * @returns {Function}
     */
    function itemsPerPageFilter(paginationService) {

        return function(collection, itemsPerPage, paginationId) {
            if (typeof (paginationId) === 'undefined') {
                paginationId = DEFAULT_ID;
            }
            if (!paginationService.isRegistered(paginationId)) {
                throw 'pagination directive: the itemsPerPage id argument (id: ' + paginationId + ') does not match a registered pagination-id.';
            }
            var end;
            var start;
            if (angular.isObject(collection)) {
                itemsPerPage = parseInt(itemsPerPage) || 9999999999;
                if (paginationService.isAsyncMode(paginationId)) {
                    start = 0;
                } else {
                    start = (paginationService.getCurrentPage(paginationId) - 1) * itemsPerPage;
                }
                end = start + itemsPerPage;
                paginationService.setItemsPerPage(paginationId, itemsPerPage);

                if (collection instanceof Array) {
                    // the array just needs to be sliced
                    return collection.slice(start, end);
                } else {
                    // in the case of an object, we need to get an array of keys, slice that, then map back to
                    // the original object.
                    var slicedObject = {};
                    angular.forEach(keys(collection).slice(start, end), function(key) {
                        slicedObject[key] = collection[key];
                    });
                    return slicedObject;
                }
            } else {
                return collection;
            }
        };
    }

    /**
     * Shim for the Object.keys() method which does not exist in IE < 9
     * @param obj
     * @returns {Array}
     */
    function keys(obj) {
        if (!Object.keys) {
            var objKeys = [];
            for (var i in obj) {
                if (obj.hasOwnProperty(i)) {
                    objKeys.push(i);
                }
            }
            return objKeys;
        } else {
            return Object.keys(obj);
        }
    }

    /**
     * This service allows the various parts of the module to communicate and stay in sync.
     */
    function paginationService() {

        var instances = {};
        var lastRegisteredInstance;

        this.registerInstance = function(instanceId) {
            if (typeof instances[instanceId] === 'undefined') {
                instances[instanceId] = {
                    asyncMode: false
                };
                lastRegisteredInstance = instanceId;
            }
        };

        this.deregisterInstance = function(instanceId) {
            delete instances[instanceId];
        };

        this.isRegistered = function(instanceId) {
            return (typeof instances[instanceId] !== 'undefined');
        };

        this.getLastInstanceId = function() {
            return lastRegisteredInstance;
        };

        this.setCurrentPageParser = function(instanceId, val, scope) {
            instances[instanceId].currentPageParser = val;
            instances[instanceId].context = scope;
        };
        this.setCurrentPage = function(instanceId, val) {
            instances[instanceId].currentPageParser.assign(instances[instanceId].context, val);
        };
        this.getCurrentPage = function(instanceId) {
            var parser = instances[instanceId].currentPageParser;
            return parser ? parser(instances[instanceId].context) : 1;
        };

        this.setItemsPerPage = function(instanceId, val) {
            instances[instanceId].itemsPerPage = val;
        };
        this.getItemsPerPage = function(instanceId) {
            return instances[instanceId].itemsPerPage;
        };

        this.setCollectionLength = function(instanceId, val) {
            instances[instanceId].collectionLength = val;
        };
        this.getCollectionLength = function(instanceId) {
            return instances[instanceId].collectionLength;
        };

        this.setAsyncModeTrue = function(instanceId) {
            instances[instanceId].asyncMode = true;
        };

        this.setAsyncModeFalse = function(instanceId) {
            instances[instanceId].asyncMode = false;
        };

        this.isAsyncMode = function(instanceId) {
            return instances[instanceId].asyncMode;
        };
    }

    /**
     * This provider allows global configuration of the template path used by the dir-pagination-controls directive.
     */
    function paginationTemplateProvider() {

        var templatePath = 'angularUtils.directives.dirPagination.template';
        var templateString;

        /**
         * Set a templateUrl to be used by all instances of <dir-pagination-controls>
         * @param {String} path
         */
        this.setPath = function(path) {
            templatePath = path;
        };

        /**
         * Set a string of HTML to be used as a template by all instances
         * of <dir-pagination-controls>. If both a path *and* a string have been set,
         * the string takes precedence.
         * @param {String} str
         */
        this.setString = function(str) {
            templateString = str;
        };

        this.$get = function() {
            return {
                getPath: function() {
                    return templatePath;
                },
                getString: function() {
                    return templateString;
                }
            };
        };
    }
})();


/* **********************************************  perfect-scrollbar **********************************************************/


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

/* perfect-scrollbar v0.6.12 */
(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
'use strict';

var ps = require('../main');
var psInstances = require('../plugin/instances');

function mountJQuery(jQuery) {
  jQuery.fn.perfectScrollbar = function (settingOrCommand) {
    return this.each(function () {
      if (typeof settingOrCommand === 'object' ||
          typeof settingOrCommand === 'undefined') {
        // If it's an object or none, initialize.
        var settings = settingOrCommand;

        if (!psInstances.get(this)) {
          ps.initialize(this, settings);
        }
      } else {
        // Unless, it may be a command.
        var command = settingOrCommand;

        if (command === 'update') {
          ps.update(this);
        } else if (command === 'destroy') {
          ps.destroy(this);
        }
      }
    });
  };
}

if (typeof define === 'function' && define.amd) {
  // AMD. Register as an anonymous module.
  define(['jquery'], mountJQuery);
} else {
  var jq = window.jQuery ? window.jQuery : window.$;
  if (typeof jq !== 'undefined') {
    mountJQuery(jq);
  }
}

module.exports = mountJQuery;

},{"../main":7,"../plugin/instances":18}],2:[function(require,module,exports){
'use strict';

function oldAdd(element, className) {
  var classes = element.className.split(' ');
  if (classes.indexOf(className) < 0) {
    classes.push(className);
  }
  element.className = classes.join(' ');
}

function oldRemove(element, className) {
  var classes = element.className.split(' ');
  var idx = classes.indexOf(className);
  if (idx >= 0) {
    classes.splice(idx, 1);
  }
  element.className = classes.join(' ');
}

exports.add = function (element, className) {
  if (element.classList) {
    element.classList.add(className);
  } else {
    oldAdd(element, className);
  }
};

exports.remove = function (element, className) {
  if (element.classList) {
    element.classList.remove(className);
  } else {
    oldRemove(element, className);
  }
};

exports.list = function (element) {
  if (element.classList) {
    return Array.prototype.slice.apply(element.classList);
  } else {
    return element.className.split(' ');
  }
};

},{}],3:[function(require,module,exports){
'use strict';

var DOM = {};

DOM.e = function (tagName, className) {
  var element = document.createElement(tagName);
  element.className = className;
  return element;
};

DOM.appendTo = function (child, parent) {
  parent.appendChild(child);
  return child;
};

function cssGet(element, styleName) {
  return window.getComputedStyle(element)[styleName];
}

function cssSet(element, styleName, styleValue) {
  if (typeof styleValue === 'number') {
    styleValue = styleValue.toString() + 'px';
  }
  element.style[styleName] = styleValue;
  return element;
}

function cssMultiSet(element, obj) {
  for (var key in obj) {
    var val = obj[key];
    if (typeof val === 'number') {
      val = val.toString() + 'px';
    }
    element.style[key] = val;
  }
  return element;
}

DOM.css = function (element, styleNameOrObject, styleValue) {
  if (typeof styleNameOrObject === 'object') {
    // multiple set with object
    return cssMultiSet(element, styleNameOrObject);
  } else {
    if (typeof styleValue === 'undefined') {
      return cssGet(element, styleNameOrObject);
    } else {
      return cssSet(element, styleNameOrObject, styleValue);
    }
  }
};

DOM.matches = function (element, query) {
  if (typeof element.matches !== 'undefined') {
    return element.matches(query);
  } else {
    if (typeof element.matchesSelector !== 'undefined') {
      return element.matchesSelector(query);
    } else if (typeof element.webkitMatchesSelector !== 'undefined') {
      return element.webkitMatchesSelector(query);
    } else if (typeof element.mozMatchesSelector !== 'undefined') {
      return element.mozMatchesSelector(query);
    } else if (typeof element.msMatchesSelector !== 'undefined') {
      return element.msMatchesSelector(query);
    }
  }
};

DOM.remove = function (element) {
  if (typeof element.remove !== 'undefined') {
    element.remove();
  } else {
    if (element.parentNode) {
      element.parentNode.removeChild(element);
    }
  }
};

DOM.queryChildren = function (element, selector) {
  return Array.prototype.filter.call(element.childNodes, function (child) {
    return DOM.matches(child, selector);
  });
};

module.exports = DOM;

},{}],4:[function(require,module,exports){
'use strict';

var EventElement = function (element) {
  this.element = element;
  this.events = {};
};

EventElement.prototype.bind = function (eventName, handler) {
  if (typeof this.events[eventName] === 'undefined') {
    this.events[eventName] = [];
  }
  this.events[eventName].push(handler);
  this.element.addEventListener(eventName, handler, false);
};

EventElement.prototype.unbind = function (eventName, handler) {
  var isHandlerProvided = (typeof handler !== 'undefined');
  this.events[eventName] = this.events[eventName].filter(function (hdlr) {
    if (isHandlerProvided && hdlr !== handler) {
      return true;
    }
    this.element.removeEventListener(eventName, hdlr, false);
    return false;
  }, this);
};

EventElement.prototype.unbindAll = function () {
  for (var name in this.events) {
    this.unbind(name);
  }
};

var EventManager = function () {
  this.eventElements = [];
};

EventManager.prototype.eventElement = function (element) {
  var ee = this.eventElements.filter(function (eventElement) {
    return eventElement.element === element;
  })[0];
  if (typeof ee === 'undefined') {
    ee = new EventElement(element);
    this.eventElements.push(ee);
  }
  return ee;
};

EventManager.prototype.bind = function (element, eventName, handler) {
  this.eventElement(element).bind(eventName, handler);
};

EventManager.prototype.unbind = function (element, eventName, handler) {
  this.eventElement(element).unbind(eventName, handler);
};

EventManager.prototype.unbindAll = function () {
  for (var i = 0; i < this.eventElements.length; i++) {
    this.eventElements[i].unbindAll();
  }
};

EventManager.prototype.once = function (element, eventName, handler) {
  var ee = this.eventElement(element);
  var onceHandler = function (e) {
    ee.unbind(eventName, onceHandler);
    handler(e);
  };
  ee.bind(eventName, onceHandler);
};

module.exports = EventManager;

},{}],5:[function(require,module,exports){
'use strict';

module.exports = (function () {
  function s4() {
    return Math.floor((1 + Math.random()) * 0x10000)
               .toString(16)
               .substring(1);
  }
  return function () {
    return s4() + s4() + '-' + s4() + '-' + s4() + '-' +
           s4() + '-' + s4() + s4() + s4();
  };
})();

},{}],6:[function(require,module,exports){
'use strict';

var cls = require('./class');
var dom = require('./dom');

var toInt = exports.toInt = function (x) {
  return parseInt(x, 10) || 0;
};

var clone = exports.clone = function (obj) {
  if (obj === null) {
    return null;
  } else if (obj.constructor === Array) {
    return obj.map(clone);
  } else if (typeof obj === 'object') {
    var result = {};
    for (var key in obj) {
      result[key] = clone(obj[key]);
    }
    return result;
  } else {
    return obj;
  }
};

exports.extend = function (original, source) {
  var result = clone(original);
  for (var key in source) {
    result[key] = clone(source[key]);
  }
  return result;
};

exports.isEditable = function (el) {
  return dom.matches(el, "input,[contenteditable]") ||
         dom.matches(el, "select,[contenteditable]") ||
         dom.matches(el, "textarea,[contenteditable]") ||
         dom.matches(el, "button,[contenteditable]");
};

exports.removePsClasses = function (element) {
  var clsList = cls.list(element);
  for (var i = 0; i < clsList.length; i++) {
    var className = clsList[i];
    if (className.indexOf('ps-') === 0) {
      cls.remove(element, className);
    }
  }
};

exports.outerWidth = function (element) {
  return toInt(dom.css(element, 'width')) +
         toInt(dom.css(element, 'paddingLeft')) +
         toInt(dom.css(element, 'paddingRight')) +
         toInt(dom.css(element, 'borderLeftWidth')) +
         toInt(dom.css(element, 'borderRightWidth'));
};

exports.startScrolling = function (element, axis) {
  cls.add(element, 'ps-in-scrolling');
  if (typeof axis !== 'undefined') {
    cls.add(element, 'ps-' + axis);
  } else {
    cls.add(element, 'ps-x');
    cls.add(element, 'ps-y');
  }
};

exports.stopScrolling = function (element, axis) {
  cls.remove(element, 'ps-in-scrolling');
  if (typeof axis !== 'undefined') {
    cls.remove(element, 'ps-' + axis);
  } else {
    cls.remove(element, 'ps-x');
    cls.remove(element, 'ps-y');
  }
};

exports.env = {
  isWebKit: 'WebkitAppearance' in document.documentElement.style,
  supportsTouch: (('ontouchstart' in window) || window.DocumentTouch && document instanceof window.DocumentTouch),
  supportsIePointer: window.navigator.msMaxTouchPoints !== null
};

},{"./class":2,"./dom":3}],7:[function(require,module,exports){
'use strict';

var destroy = require('./plugin/destroy');
var initialize = require('./plugin/initialize');
var update = require('./plugin/update');

module.exports = {
  initialize: initialize,
  update: update,
  destroy: destroy
};

},{"./plugin/destroy":9,"./plugin/initialize":17,"./plugin/update":21}],8:[function(require,module,exports){
'use strict';

module.exports = {
  handlers: ['click-rail', 'drag-scrollbar', 'keyboard', 'wheel', 'touch'],
  maxScrollbarLength: null,
  minScrollbarLength: null,
  scrollXMarginOffset: 0,
  scrollYMarginOffset: 0,
  stopPropagationOnClick: true,
  suppressScrollX: false,
  suppressScrollY: false,
  swipePropagation: true,
  useBothWheelAxes: false,
  wheelPropagation: false,
  wheelSpeed: 1,
  theme: 'default'
};

},{}],9:[function(require,module,exports){
'use strict';

var _ = require('../lib/helper');
var dom = require('../lib/dom');
var instances = require('./instances');

module.exports = function (element) {
  var i = instances.get(element);

  if (!i) {
    return;
  }

  i.event.unbindAll();
  dom.remove(i.scrollbarX);
  dom.remove(i.scrollbarY);
  dom.remove(i.scrollbarXRail);
  dom.remove(i.scrollbarYRail);
  _.removePsClasses(element);

  instances.remove(element);
};

},{"../lib/dom":3,"../lib/helper":6,"./instances":18}],10:[function(require,module,exports){
'use strict';

var _ = require('../../lib/helper');
var instances = require('../instances');
var updateGeometry = require('../update-geometry');
var updateScroll = require('../update-scroll');

function bindClickRailHandler(element, i) {
  function pageOffset(el) {
    return el.getBoundingClientRect();
  }
  var stopPropagation = function (e) { e.stopPropagation(); };

  if (i.settings.stopPropagationOnClick) {
    i.event.bind(i.scrollbarY, 'click', stopPropagation);
  }
  i.event.bind(i.scrollbarYRail, 'click', function (e) {
    var halfOfScrollbarLength = _.toInt(i.scrollbarYHeight / 2);
    var positionTop = i.railYRatio * (e.pageY - window.pageYOffset - pageOffset(i.scrollbarYRail).top - halfOfScrollbarLength);
    var maxPositionTop = i.railYRatio * (i.railYHeight - i.scrollbarYHeight);
    var positionRatio = positionTop / maxPositionTop;

    if (positionRatio < 0) {
      positionRatio = 0;
    } else if (positionRatio > 1) {
      positionRatio = 1;
    }

    updateScroll(element, 'top', (i.contentHeight - i.containerHeight) * positionRatio);
    updateGeometry(element);

    e.stopPropagation();
  });

  if (i.settings.stopPropagationOnClick) {
    i.event.bind(i.scrollbarX, 'click', stopPropagation);
  }
  i.event.bind(i.scrollbarXRail, 'click', function (e) {
    var halfOfScrollbarLength = _.toInt(i.scrollbarXWidth / 2);
    var positionLeft = i.railXRatio * (e.pageX - window.pageXOffset - pageOffset(i.scrollbarXRail).left - halfOfScrollbarLength);
    var maxPositionLeft = i.railXRatio * (i.railXWidth - i.scrollbarXWidth);
    var positionRatio = positionLeft / maxPositionLeft;

    if (positionRatio < 0) {
      positionRatio = 0;
    } else if (positionRatio > 1) {
      positionRatio = 1;
    }

    updateScroll(element, 'left', ((i.contentWidth - i.containerWidth) * positionRatio) - i.negativeScrollAdjustment);
    updateGeometry(element);

    e.stopPropagation();
  });
}

module.exports = function (element) {
  var i = instances.get(element);
  bindClickRailHandler(element, i);
};

},{"../../lib/helper":6,"../instances":18,"../update-geometry":19,"../update-scroll":20}],11:[function(require,module,exports){
'use strict';

var _ = require('../../lib/helper');
var dom = require('../../lib/dom');
var instances = require('../instances');
var updateGeometry = require('../update-geometry');
var updateScroll = require('../update-scroll');

function bindMouseScrollXHandler(element, i) {
  var currentLeft = null;
  var currentPageX = null;

  function updateScrollLeft(deltaX) {
    var newLeft = currentLeft + (deltaX * i.railXRatio);
    var maxLeft = Math.max(0, i.scrollbarXRail.getBoundingClientRect().left) + (i.railXRatio * (i.railXWidth - i.scrollbarXWidth));

    if (newLeft < 0) {
      i.scrollbarXLeft = 0;
    } else if (newLeft > maxLeft) {
      i.scrollbarXLeft = maxLeft;
    } else {
      i.scrollbarXLeft = newLeft;
    }

    var scrollLeft = _.toInt(i.scrollbarXLeft * (i.contentWidth - i.containerWidth) / (i.containerWidth - (i.railXRatio * i.scrollbarXWidth))) - i.negativeScrollAdjustment;
    updateScroll(element, 'left', scrollLeft);
  }

  var mouseMoveHandler = function (e) {
    updateScrollLeft(e.pageX - currentPageX);
    updateGeometry(element);
    e.stopPropagation();
    e.preventDefault();
  };

  var mouseUpHandler = function () {
    _.stopScrolling(element, 'x');
    i.event.unbind(i.ownerDocument, 'mousemove', mouseMoveHandler);
  };

  i.event.bind(i.scrollbarX, 'mousedown', function (e) {
    currentPageX = e.pageX;
    currentLeft = _.toInt(dom.css(i.scrollbarX, 'left')) * i.railXRatio;
    _.startScrolling(element, 'x');

    i.event.bind(i.ownerDocument, 'mousemove', mouseMoveHandler);
    i.event.once(i.ownerDocument, 'mouseup', mouseUpHandler);

    e.stopPropagation();
    e.preventDefault();
  });
}

function bindMouseScrollYHandler(element, i) {
  var currentTop = null;
  var currentPageY = null;

  function updateScrollTop(deltaY) {
    var newTop = currentTop + (deltaY * i.railYRatio);
    var maxTop = Math.max(0, i.scrollbarYRail.getBoundingClientRect().top) + (i.railYRatio * (i.railYHeight - i.scrollbarYHeight));

    if (newTop < 0) {
      i.scrollbarYTop = 0;
    } else if (newTop > maxTop) {
      i.scrollbarYTop = maxTop;
    } else {
      i.scrollbarYTop = newTop;
    }

    var scrollTop = _.toInt(i.scrollbarYTop * (i.contentHeight - i.containerHeight) / (i.containerHeight - (i.railYRatio * i.scrollbarYHeight)));
    updateScroll(element, 'top', scrollTop);
  }

  var mouseMoveHandler = function (e) {
    updateScrollTop(e.pageY - currentPageY);
    updateGeometry(element);
    e.stopPropagation();
    e.preventDefault();
  };

  var mouseUpHandler = function () {
    _.stopScrolling(element, 'y');
    i.event.unbind(i.ownerDocument, 'mousemove', mouseMoveHandler);
  };

  i.event.bind(i.scrollbarY, 'mousedown', function (e) {
    currentPageY = e.pageY;
    currentTop = _.toInt(dom.css(i.scrollbarY, 'top')) * i.railYRatio;
    _.startScrolling(element, 'y');

    i.event.bind(i.ownerDocument, 'mousemove', mouseMoveHandler);
    i.event.once(i.ownerDocument, 'mouseup', mouseUpHandler);

    e.stopPropagation();
    e.preventDefault();
  });
}

module.exports = function (element) {
  var i = instances.get(element);
  bindMouseScrollXHandler(element, i);
  bindMouseScrollYHandler(element, i);
};

},{"../../lib/dom":3,"../../lib/helper":6,"../instances":18,"../update-geometry":19,"../update-scroll":20}],12:[function(require,module,exports){
'use strict';

var _ = require('../../lib/helper');
var dom = require('../../lib/dom');
var instances = require('../instances');
var updateGeometry = require('../update-geometry');
var updateScroll = require('../update-scroll');

function bindKeyboardHandler(element, i) {
  var hovered = false;
  i.event.bind(element, 'mouseenter', function () {
    hovered = true;
  });
  i.event.bind(element, 'mouseleave', function () {
    hovered = false;
  });

  var shouldPrevent = false;
  function shouldPreventDefault(deltaX, deltaY) {
    var scrollTop = element.scrollTop;
    if (deltaX === 0) {
      if (!i.scrollbarYActive) {
        return false;
      }
      if ((scrollTop === 0 && deltaY > 0) || (scrollTop >= i.contentHeight - i.containerHeight && deltaY < 0)) {
        return !i.settings.wheelPropagation;
      }
    }

    var scrollLeft = element.scrollLeft;
    if (deltaY === 0) {
      if (!i.scrollbarXActive) {
        return false;
      }
      if ((scrollLeft === 0 && deltaX < 0) || (scrollLeft >= i.contentWidth - i.containerWidth && deltaX > 0)) {
        return !i.settings.wheelPropagation;
      }
    }
    return true;
  }

  i.event.bind(i.ownerDocument, 'keydown', function (e) {
    if ((e.isDefaultPrevented && e.isDefaultPrevented()) || e.defaultPrevented) {
      return;
    }

    var focused = dom.matches(i.scrollbarX, ':focus') ||
                  dom.matches(i.scrollbarY, ':focus');

    if (!hovered && !focused) {
      return;
    }

    var activeElement = document.activeElement ? document.activeElement : i.ownerDocument.activeElement;
    if (activeElement) {
      if (activeElement.tagName === 'IFRAME') {
        activeElement = activeElement.contentDocument.activeElement;
      } else {
        // go deeper if element is a webcomponent
        while (activeElement.shadowRoot) {
          activeElement = activeElement.shadowRoot.activeElement;
        }
      }
      if (_.isEditable(activeElement)) {
        return;
      }
    }

    var deltaX = 0;
    var deltaY = 0;

    switch (e.which) {
    case 37: // left
      deltaX = -30;
      break;
    case 38: // up
      deltaY = 30;
      break;
    case 39: // right
      deltaX = 30;
      break;
    case 40: // down
      deltaY = -30;
      break;
    case 33: // page up
      deltaY = 90;
      break;
    case 32: // space bar
      if (e.shiftKey) {
        deltaY = 90;
      } else {
        deltaY = -90;
      }
      break;
    case 34: // page down
      deltaY = -90;
      break;
    case 35: // end
      if (e.ctrlKey) {
        deltaY = -i.contentHeight;
      } else {
        deltaY = -i.containerHeight;
      }
      break;
    case 36: // home
      if (e.ctrlKey) {
        deltaY = element.scrollTop;
      } else {
        deltaY = i.containerHeight;
      }
      break;
    default:
      return;
    }

    updateScroll(element, 'top', element.scrollTop - deltaY);
    updateScroll(element, 'left', element.scrollLeft + deltaX);
    updateGeometry(element);

    shouldPrevent = shouldPreventDefault(deltaX, deltaY);
    if (shouldPrevent) {
      e.preventDefault();
    }
  });
}

module.exports = function (element) {
  var i = instances.get(element);
  bindKeyboardHandler(element, i);
};

},{"../../lib/dom":3,"../../lib/helper":6,"../instances":18,"../update-geometry":19,"../update-scroll":20}],13:[function(require,module,exports){
'use strict';

var instances = require('../instances');
var updateGeometry = require('../update-geometry');
var updateScroll = require('../update-scroll');

function bindMouseWheelHandler(element, i) {
  var shouldPrevent = false;

  function shouldPreventDefault(deltaX, deltaY) {
    var scrollTop = element.scrollTop;
    if (deltaX === 0) {
      if (!i.scrollbarYActive) {
        return false;
      }
      if ((scrollTop === 0 && deltaY > 0) || (scrollTop >= i.contentHeight - i.containerHeight && deltaY < 0)) {
        return !i.settings.wheelPropagation;
      }
    }

    var scrollLeft = element.scrollLeft;
    if (deltaY === 0) {
      if (!i.scrollbarXActive) {
        return false;
      }
      if ((scrollLeft === 0 && deltaX < 0) || (scrollLeft >= i.contentWidth - i.containerWidth && deltaX > 0)) {
        return !i.settings.wheelPropagation;
      }
    }
    return true;
  }

  function getDeltaFromEvent(e) {
    var deltaX = e.deltaX;
    var deltaY = -1 * e.deltaY;

    if (typeof deltaX === "undefined" || typeof deltaY === "undefined") {
      // OS X Safari
      deltaX = -1 * e.wheelDeltaX / 6;
      deltaY = e.wheelDeltaY / 6;
    }

    if (e.deltaMode && e.deltaMode === 1) {
      // Firefox in deltaMode 1: Line scrolling
      deltaX *= 10;
      deltaY *= 10;
    }

    if (deltaX !== deltaX && deltaY !== deltaY/* NaN checks */) {
      // IE in some mouse drivers
      deltaX = 0;
      deltaY = e.wheelDelta;
    }

    return [deltaX, deltaY];
  }

  function shouldBeConsumedByChild(deltaX, deltaY) {
    var child = element.querySelector('textarea:hover, select[multiple]:hover, .ps-child:hover');
    if (child) {
      if (child.tagName !== 'TEXTAREA' && !window.getComputedStyle(child).overflow.match(/(scroll|auto)/)) {
        return false;
      }

      var maxScrollTop = child.scrollHeight - child.clientHeight;
      if (maxScrollTop > 0) {
        if (!(child.scrollTop === 0 && deltaY > 0) && !(child.scrollTop === maxScrollTop && deltaY < 0)) {
          return true;
        }
      }
      var maxScrollLeft = child.scrollLeft - child.clientWidth;
      if (maxScrollLeft > 0) {
        if (!(child.scrollLeft === 0 && deltaX < 0) && !(child.scrollLeft === maxScrollLeft && deltaX > 0)) {
          return true;
        }
      }
    }
    return false;
  }

  function mousewheelHandler(e) {
    var delta = getDeltaFromEvent(e);

    var deltaX = delta[0];
    var deltaY = delta[1];

    if (shouldBeConsumedByChild(deltaX, deltaY)) {
      return;
    }

    shouldPrevent = false;
    if (!i.settings.useBothWheelAxes) {
      // deltaX will only be used for horizontal scrolling and deltaY will
      // only be used for vertical scrolling - this is the default
      updateScroll(element, 'top', element.scrollTop - (deltaY * i.settings.wheelSpeed));
      updateScroll(element, 'left', element.scrollLeft + (deltaX * i.settings.wheelSpeed));
    } else if (i.scrollbarYActive && !i.scrollbarXActive) {
      // only vertical scrollbar is active and useBothWheelAxes option is
      // active, so let's scroll vertical bar using both mouse wheel axes
      if (deltaY) {
        updateScroll(element, 'top', element.scrollTop - (deltaY * i.settings.wheelSpeed));
      } else {
        updateScroll(element, 'top', element.scrollTop + (deltaX * i.settings.wheelSpeed));
      }
      shouldPrevent = true;
    } else if (i.scrollbarXActive && !i.scrollbarYActive) {
      // useBothWheelAxes and only horizontal bar is active, so use both
      // wheel axes for horizontal bar
      if (deltaX) {
        updateScroll(element, 'left', element.scrollLeft + (deltaX * i.settings.wheelSpeed));
      } else {
        updateScroll(element, 'left', element.scrollLeft - (deltaY * i.settings.wheelSpeed));
      }
      shouldPrevent = true;
    }

    updateGeometry(element);

    shouldPrevent = (shouldPrevent || shouldPreventDefault(deltaX, deltaY));
    if (shouldPrevent) {
      e.stopPropagation();
      e.preventDefault();
    }
  }

  if (typeof window.onwheel !== "undefined") {
    i.event.bind(element, 'wheel', mousewheelHandler);
  } else if (typeof window.onmousewheel !== "undefined") {
    i.event.bind(element, 'mousewheel', mousewheelHandler);
  }
}

module.exports = function (element) {
  var i = instances.get(element);
  bindMouseWheelHandler(element, i);
};

},{"../instances":18,"../update-geometry":19,"../update-scroll":20}],14:[function(require,module,exports){
'use strict';

var instances = require('../instances');
var updateGeometry = require('../update-geometry');

function bindNativeScrollHandler(element, i) {
  i.event.bind(element, 'scroll', function () {
    updateGeometry(element);
  });
}

module.exports = function (element) {
  var i = instances.get(element);
  bindNativeScrollHandler(element, i);
};

},{"../instances":18,"../update-geometry":19}],15:[function(require,module,exports){
'use strict';

var _ = require('../../lib/helper');
var instances = require('../instances');
var updateGeometry = require('../update-geometry');
var updateScroll = require('../update-scroll');

function bindSelectionHandler(element, i) {
  function getRangeNode() {
    var selection = window.getSelection ? window.getSelection() :
                    document.getSelection ? document.getSelection() : '';
    if (selection.toString().length === 0) {
      return null;
    } else {
      return selection.getRangeAt(0).commonAncestorContainer;
    }
  }

  var scrollingLoop = null;
  var scrollDiff = {top: 0, left: 0};
  function startScrolling() {
    if (!scrollingLoop) {
      scrollingLoop = setInterval(function () {
        if (!instances.get(element)) {
          clearInterval(scrollingLoop);
          return;
        }

        updateScroll(element, 'top', element.scrollTop + scrollDiff.top);
        updateScroll(element, 'left', element.scrollLeft + scrollDiff.left);
        updateGeometry(element);
      }, 50); // every .1 sec
    }
  }
  function stopScrolling() {
    if (scrollingLoop) {
      clearInterval(scrollingLoop);
      scrollingLoop = null;
    }
    _.stopScrolling(element);
  }

  var isSelected = false;
  i.event.bind(i.ownerDocument, 'selectionchange', function () {
    if (element.contains(getRangeNode())) {
      isSelected = true;
    } else {
      isSelected = false;
      stopScrolling();
    }
  });
  i.event.bind(window, 'mouseup', function () {
    if (isSelected) {
      isSelected = false;
      stopScrolling();
    }
  });

  i.event.bind(window, 'mousemove', function (e) {
    if (isSelected) {
      var mousePosition = {x: e.pageX, y: e.pageY};
      var containerGeometry = {
        left: element.offsetLeft,
        right: element.offsetLeft + element.offsetWidth,
        top: element.offsetTop,
        bottom: element.offsetTop + element.offsetHeight
      };

      if (mousePosition.x < containerGeometry.left + 3) {
        scrollDiff.left = -5;
        _.startScrolling(element, 'x');
      } else if (mousePosition.x > containerGeometry.right - 3) {
        scrollDiff.left = 5;
        _.startScrolling(element, 'x');
      } else {
        scrollDiff.left = 0;
      }

      if (mousePosition.y < containerGeometry.top + 3) {
        if (containerGeometry.top + 3 - mousePosition.y < 5) {
          scrollDiff.top = -5;
        } else {
          scrollDiff.top = -20;
        }
        _.startScrolling(element, 'y');
      } else if (mousePosition.y > containerGeometry.bottom - 3) {
        if (mousePosition.y - containerGeometry.bottom + 3 < 5) {
          scrollDiff.top = 5;
        } else {
          scrollDiff.top = 20;
        }
        _.startScrolling(element, 'y');
      } else {
        scrollDiff.top = 0;
      }

      if (scrollDiff.top === 0 && scrollDiff.left === 0) {
        stopScrolling();
      } else {
        startScrolling();
      }
    }
  });
}

module.exports = function (element) {
  var i = instances.get(element);
  bindSelectionHandler(element, i);
};

},{"../../lib/helper":6,"../instances":18,"../update-geometry":19,"../update-scroll":20}],16:[function(require,module,exports){
'use strict';

var _ = require('../../lib/helper');
var instances = require('../instances');
var updateGeometry = require('../update-geometry');
var updateScroll = require('../update-scroll');

function bindTouchHandler(element, i, supportsTouch, supportsIePointer) {
  function shouldPreventDefault(deltaX, deltaY) {
    var scrollTop = element.scrollTop;
    var scrollLeft = element.scrollLeft;
    var magnitudeX = Math.abs(deltaX);
    var magnitudeY = Math.abs(deltaY);

    if (magnitudeY > magnitudeX) {
      // user is perhaps trying to swipe up/down the page

      if (((deltaY < 0) && (scrollTop === i.contentHeight - i.containerHeight)) ||
          ((deltaY > 0) && (scrollTop === 0))) {
        return !i.settings.swipePropagation;
      }
    } else if (magnitudeX > magnitudeY) {
      // user is perhaps trying to swipe left/right across the page

      if (((deltaX < 0) && (scrollLeft === i.contentWidth - i.containerWidth)) ||
          ((deltaX > 0) && (scrollLeft === 0))) {
        return !i.settings.swipePropagation;
      }
    }

    return true;
  }

  function applyTouchMove(differenceX, differenceY) {
    updateScroll(element, 'top', element.scrollTop - differenceY);
    updateScroll(element, 'left', element.scrollLeft - differenceX);

    updateGeometry(element);
  }

  var startOffset = {};
  var startTime = 0;
  var speed = {};
  var easingLoop = null;
  var inGlobalTouch = false;
  var inLocalTouch = false;

  function globalTouchStart() {
    inGlobalTouch = true;
  }
  function globalTouchEnd() {
    inGlobalTouch = false;
  }

  function getTouch(e) {
    if (e.targetTouches) {
      return e.targetTouches[0];
    } else {
      // Maybe IE pointer
      return e;
    }
  }
  function shouldHandle(e) {
    if (e.targetTouches && e.targetTouches.length === 1) {
      return true;
    }
    if (e.pointerType && e.pointerType !== 'mouse' && e.pointerType !== e.MSPOINTER_TYPE_MOUSE) {
      return true;
    }
    return false;
  }
  function touchStart(e) {
    if (shouldHandle(e)) {
      inLocalTouch = true;

      var touch = getTouch(e);

      startOffset.pageX = touch.pageX;
      startOffset.pageY = touch.pageY;

      startTime = (new Date()).getTime();

      if (easingLoop !== null) {
        clearInterval(easingLoop);
      }

      e.stopPropagation();
    }
  }
  function touchMove(e) {
    if (!inLocalTouch && i.settings.swipePropagation) {
      touchStart(e);
    }
    if (!inGlobalTouch && inLocalTouch && shouldHandle(e)) {
      var touch = getTouch(e);

      var currentOffset = {pageX: touch.pageX, pageY: touch.pageY};

      var differenceX = currentOffset.pageX - startOffset.pageX;
      var differenceY = currentOffset.pageY - startOffset.pageY;

      applyTouchMove(differenceX, differenceY);
      startOffset = currentOffset;

      var currentTime = (new Date()).getTime();

      var timeGap = currentTime - startTime;
      if (timeGap > 0) {
        speed.x = differenceX / timeGap;
        speed.y = differenceY / timeGap;
        startTime = currentTime;
      }

      if (shouldPreventDefault(differenceX, differenceY)) {
        e.stopPropagation();
        e.preventDefault();
      }
    }
  }
  function touchEnd() {
    if (!inGlobalTouch && inLocalTouch) {
      inLocalTouch = false;

      clearInterval(easingLoop);
      easingLoop = setInterval(function () {
        if (!instances.get(element)) {
          clearInterval(easingLoop);
          return;
        }

        if (Math.abs(speed.x) < 0.01 && Math.abs(speed.y) < 0.01) {
          clearInterval(easingLoop);
          return;
        }

        applyTouchMove(speed.x * 30, speed.y * 30);

        speed.x *= 0.8;
        speed.y *= 0.8;
      }, 10);
    }
  }

  if (supportsTouch) {
    i.event.bind(window, 'touchstart', globalTouchStart);
    i.event.bind(window, 'touchend', globalTouchEnd);
    i.event.bind(element, 'touchstart', touchStart);
    i.event.bind(element, 'touchmove', touchMove);
    i.event.bind(element, 'touchend', touchEnd);
  }

  if (supportsIePointer) {
    if (window.PointerEvent) {
      i.event.bind(window, 'pointerdown', globalTouchStart);
      i.event.bind(window, 'pointerup', globalTouchEnd);
      i.event.bind(element, 'pointerdown', touchStart);
      i.event.bind(element, 'pointermove', touchMove);
      i.event.bind(element, 'pointerup', touchEnd);
    } else if (window.MSPointerEvent) {
      i.event.bind(window, 'MSPointerDown', globalTouchStart);
      i.event.bind(window, 'MSPointerUp', globalTouchEnd);
      i.event.bind(element, 'MSPointerDown', touchStart);
      i.event.bind(element, 'MSPointerMove', touchMove);
      i.event.bind(element, 'MSPointerUp', touchEnd);
    }
  }
}

module.exports = function (element) {
  if (!_.env.supportsTouch && !_.env.supportsIePointer) {
    return;
  }

  var i = instances.get(element);
  bindTouchHandler(element, i, _.env.supportsTouch, _.env.supportsIePointer);
};

},{"../../lib/helper":6,"../instances":18,"../update-geometry":19,"../update-scroll":20}],17:[function(require,module,exports){
'use strict';

var _ = require('../lib/helper');
var cls = require('../lib/class');
var instances = require('./instances');
var updateGeometry = require('./update-geometry');

// Handlers
var handlers = {
  'click-rail': require('./handler/click-rail'),
  'drag-scrollbar': require('./handler/drag-scrollbar'),
  'keyboard': require('./handler/keyboard'),
  'wheel': require('./handler/mouse-wheel'),
  'touch': require('./handler/touch'),
  'selection': require('./handler/selection')
};
var nativeScrollHandler = require('./handler/native-scroll');

module.exports = function (element, userSettings) {
  userSettings = typeof userSettings === 'object' ? userSettings : {};

  cls.add(element, 'ps-container');

  // Create a plugin instance.
  var i = instances.add(element);

  i.settings = _.extend(i.settings, userSettings);
  cls.add(element, 'ps-theme-' + i.settings.theme);

  i.settings.handlers.forEach(function (handlerName) {
    handlers[handlerName](element);
  });

  nativeScrollHandler(element);

  updateGeometry(element);
};

},{"../lib/class":2,"../lib/helper":6,"./handler/click-rail":10,"./handler/drag-scrollbar":11,"./handler/keyboard":12,"./handler/mouse-wheel":13,"./handler/native-scroll":14,"./handler/selection":15,"./handler/touch":16,"./instances":18,"./update-geometry":19}],18:[function(require,module,exports){
'use strict';

var _ = require('../lib/helper');
var cls = require('../lib/class');
var defaultSettings = require('./default-setting');
var dom = require('../lib/dom');
var EventManager = require('../lib/event-manager');
var guid = require('../lib/guid');

var instances = {};

function Instance(element) {
  var i = this;

  i.settings = _.clone(defaultSettings);
  i.containerWidth = null;
  i.containerHeight = null;
  i.contentWidth = null;
  i.contentHeight = null;

  i.isRtl = dom.css(element, 'direction') === "rtl";
  i.isNegativeScroll = (function () {
    var originalScrollLeft = element.scrollLeft;
    var result = null;
    element.scrollLeft = -1;
    result = element.scrollLeft < 0;
    element.scrollLeft = originalScrollLeft;
    return result;
  })();
  i.negativeScrollAdjustment = i.isNegativeScroll ? element.scrollWidth - element.clientWidth : 0;
  i.event = new EventManager();
  i.ownerDocument = element.ownerDocument || document;

  function focus() {
    cls.add(element, 'ps-focus');
  }

  function blur() {
    cls.remove(element, 'ps-focus');
  }

  i.scrollbarXRail = dom.appendTo(dom.e('div', 'ps-scrollbar-x-rail'), element);
  i.scrollbarX = dom.appendTo(dom.e('div', 'ps-scrollbar-x'), i.scrollbarXRail);
  i.scrollbarX.setAttribute('tabindex', 0);
  i.event.bind(i.scrollbarX, 'focus', focus);
  i.event.bind(i.scrollbarX, 'blur', blur);
  i.scrollbarXActive = null;
  i.scrollbarXWidth = null;
  i.scrollbarXLeft = null;
  i.scrollbarXBottom = _.toInt(dom.css(i.scrollbarXRail, 'bottom'));
  i.isScrollbarXUsingBottom = i.scrollbarXBottom === i.scrollbarXBottom; // !isNaN
  i.scrollbarXTop = i.isScrollbarXUsingBottom ? null : _.toInt(dom.css(i.scrollbarXRail, 'top'));
  i.railBorderXWidth = _.toInt(dom.css(i.scrollbarXRail, 'borderLeftWidth')) + _.toInt(dom.css(i.scrollbarXRail, 'borderRightWidth'));
  // Set rail to display:block to calculate margins
  dom.css(i.scrollbarXRail, 'display', 'block');
  i.railXMarginWidth = _.toInt(dom.css(i.scrollbarXRail, 'marginLeft')) + _.toInt(dom.css(i.scrollbarXRail, 'marginRight'));
  dom.css(i.scrollbarXRail, 'display', '');
  i.railXWidth = null;
  i.railXRatio = null;

  i.scrollbarYRail = dom.appendTo(dom.e('div', 'ps-scrollbar-y-rail'), element);
  i.scrollbarY = dom.appendTo(dom.e('div', 'ps-scrollbar-y'), i.scrollbarYRail);
  i.scrollbarY.setAttribute('tabindex', 0);
  i.event.bind(i.scrollbarY, 'focus', focus);
  i.event.bind(i.scrollbarY, 'blur', blur);
  i.scrollbarYActive = null;
  i.scrollbarYHeight = null;
  i.scrollbarYTop = null;
  i.scrollbarYRight = _.toInt(dom.css(i.scrollbarYRail, 'right'));
  i.isScrollbarYUsingRight = i.scrollbarYRight === i.scrollbarYRight; // !isNaN
  i.scrollbarYLeft = i.isScrollbarYUsingRight ? null : _.toInt(dom.css(i.scrollbarYRail, 'left'));
  i.scrollbarYOuterWidth = i.isRtl ? _.outerWidth(i.scrollbarY) : null;
  i.railBorderYWidth = _.toInt(dom.css(i.scrollbarYRail, 'borderTopWidth')) + _.toInt(dom.css(i.scrollbarYRail, 'borderBottomWidth'));
  dom.css(i.scrollbarYRail, 'display', 'block');
  i.railYMarginHeight = _.toInt(dom.css(i.scrollbarYRail, 'marginTop')) + _.toInt(dom.css(i.scrollbarYRail, 'marginBottom'));
  dom.css(i.scrollbarYRail, 'display', '');
  i.railYHeight = null;
  i.railYRatio = null;
}

function getId(element) {
  return element.getAttribute('data-ps-id');
}

function setId(element, id) {
  element.setAttribute('data-ps-id', id);
}

function removeId(element) {
  element.removeAttribute('data-ps-id');
}

exports.add = function (element) {
  var newId = guid();
  setId(element, newId);
  instances[newId] = new Instance(element);
  return instances[newId];
};

exports.remove = function (element) {
  delete instances[getId(element)];
  removeId(element);
};

exports.get = function (element) {
  return instances[getId(element)];
};

},{"../lib/class":2,"../lib/dom":3,"../lib/event-manager":4,"../lib/guid":5,"../lib/helper":6,"./default-setting":8}],19:[function(require,module,exports){
'use strict';

var _ = require('../lib/helper');
var cls = require('../lib/class');
var dom = require('../lib/dom');
var instances = require('./instances');
var updateScroll = require('./update-scroll');

function getThumbSize(i, thumbSize) {
  if (i.settings.minScrollbarLength) {
    thumbSize = Math.max(thumbSize, i.settings.minScrollbarLength);
  }
  if (i.settings.maxScrollbarLength) {
    thumbSize = Math.min(thumbSize, i.settings.maxScrollbarLength);
  }
  return thumbSize;
}

function updateCss(element, i) {
  var xRailOffset = {width: i.railXWidth};
  if (i.isRtl) {
    xRailOffset.left = i.negativeScrollAdjustment + element.scrollLeft + i.containerWidth - i.contentWidth;
  } else {
    xRailOffset.left = element.scrollLeft;
  }
  if (i.isScrollbarXUsingBottom) {
    xRailOffset.bottom = i.scrollbarXBottom - element.scrollTop;
  } else {
    xRailOffset.top = i.scrollbarXTop + element.scrollTop;
  }
  dom.css(i.scrollbarXRail, xRailOffset);

  var yRailOffset = {top: element.scrollTop, height: i.railYHeight};
  if (i.isScrollbarYUsingRight) {
    if (i.isRtl) {
      yRailOffset.right = i.contentWidth - (i.negativeScrollAdjustment + element.scrollLeft) - i.scrollbarYRight - i.scrollbarYOuterWidth;
    } else {
      yRailOffset.right = i.scrollbarYRight - element.scrollLeft;
    }
  } else {
    if (i.isRtl) {
      yRailOffset.left = i.negativeScrollAdjustment + element.scrollLeft + i.containerWidth * 2 - i.contentWidth - i.scrollbarYLeft - i.scrollbarYOuterWidth;
    } else {
      yRailOffset.left = i.scrollbarYLeft + element.scrollLeft;
    }
  }
  dom.css(i.scrollbarYRail, yRailOffset);

  dom.css(i.scrollbarX, {left: i.scrollbarXLeft, width: i.scrollbarXWidth - i.railBorderXWidth});
  dom.css(i.scrollbarY, {top: i.scrollbarYTop, height: i.scrollbarYHeight - i.railBorderYWidth});
}

module.exports = function (element) {
  var i = instances.get(element);

  i.containerWidth = element.clientWidth;
  i.containerHeight = element.clientHeight;
  i.contentWidth = element.scrollWidth;
  i.contentHeight = element.scrollHeight;

  var existingRails;
  if (!element.contains(i.scrollbarXRail)) {
    existingRails = dom.queryChildren(element, '.ps-scrollbar-x-rail');
    if (existingRails.length > 0) {
      existingRails.forEach(function (rail) {
        dom.remove(rail);
      });
    }
    dom.appendTo(i.scrollbarXRail, element);
  }
  if (!element.contains(i.scrollbarYRail)) {
    existingRails = dom.queryChildren(element, '.ps-scrollbar-y-rail');
    if (existingRails.length > 0) {
      existingRails.forEach(function (rail) {
        dom.remove(rail);
      });
    }
    dom.appendTo(i.scrollbarYRail, element);
  }

  if (!i.settings.suppressScrollX && i.containerWidth + i.settings.scrollXMarginOffset < i.contentWidth) {
    i.scrollbarXActive = true;
    i.railXWidth = i.containerWidth - i.railXMarginWidth;
    i.railXRatio = i.containerWidth / i.railXWidth;
    i.scrollbarXWidth = getThumbSize(i, _.toInt(i.railXWidth * i.containerWidth / i.contentWidth));
    i.scrollbarXLeft = _.toInt((i.negativeScrollAdjustment + element.scrollLeft) * (i.railXWidth - i.scrollbarXWidth) / (i.contentWidth - i.containerWidth));
  } else {
    i.scrollbarXActive = false;
  }

  if (!i.settings.suppressScrollY && i.containerHeight + i.settings.scrollYMarginOffset < i.contentHeight) {
    i.scrollbarYActive = true;
    i.railYHeight = i.containerHeight - i.railYMarginHeight;
    i.railYRatio = i.containerHeight / i.railYHeight;
    i.scrollbarYHeight = getThumbSize(i, _.toInt(i.railYHeight * i.containerHeight / i.contentHeight));
    i.scrollbarYTop = _.toInt(element.scrollTop * (i.railYHeight - i.scrollbarYHeight) / (i.contentHeight - i.containerHeight));
  } else {
    i.scrollbarYActive = false;
  }

  if (i.scrollbarXLeft >= i.railXWidth - i.scrollbarXWidth) {
    i.scrollbarXLeft = i.railXWidth - i.scrollbarXWidth;
  }
  if (i.scrollbarYTop >= i.railYHeight - i.scrollbarYHeight) {
    i.scrollbarYTop = i.railYHeight - i.scrollbarYHeight;
  }

  updateCss(element, i);

  if (i.scrollbarXActive) {
    cls.add(element, 'ps-active-x');
  } else {
    cls.remove(element, 'ps-active-x');
    i.scrollbarXWidth = 0;
    i.scrollbarXLeft = 0;
    updateScroll(element, 'left', 0);
  }
  if (i.scrollbarYActive) {
    cls.add(element, 'ps-active-y');
  } else {
    cls.remove(element, 'ps-active-y');
    i.scrollbarYHeight = 0;
    i.scrollbarYTop = 0;
    updateScroll(element, 'top', 0);
  }
};

},{"../lib/class":2,"../lib/dom":3,"../lib/helper":6,"./instances":18,"./update-scroll":20}],20:[function(require,module,exports){
'use strict';

var instances = require('./instances');

var upEvent = document.createEvent('Event');
var downEvent = document.createEvent('Event');
var leftEvent = document.createEvent('Event');
var rightEvent = document.createEvent('Event');
var yEvent = document.createEvent('Event');
var xEvent = document.createEvent('Event');
var xStartEvent = document.createEvent('Event');
var xEndEvent = document.createEvent('Event');
var yStartEvent = document.createEvent('Event');
var yEndEvent = document.createEvent('Event');
var lastTop;
var lastLeft;

upEvent.initEvent('ps-scroll-up', true, true);
downEvent.initEvent('ps-scroll-down', true, true);
leftEvent.initEvent('ps-scroll-left', true, true);
rightEvent.initEvent('ps-scroll-right', true, true);
yEvent.initEvent('ps-scroll-y', true, true);
xEvent.initEvent('ps-scroll-x', true, true);
xStartEvent.initEvent('ps-x-reach-start', true, true);
xEndEvent.initEvent('ps-x-reach-end', true, true);
yStartEvent.initEvent('ps-y-reach-start', true, true);
yEndEvent.initEvent('ps-y-reach-end', true, true);

module.exports = function (element, axis, value) {
  if (typeof element === 'undefined') {
    throw 'You must provide an element to the update-scroll function';
  }

  if (typeof axis === 'undefined') {
    throw 'You must provide an axis to the update-scroll function';
  }

  if (typeof value === 'undefined') {
    throw 'You must provide a value to the update-scroll function';
  }

  if (axis === 'top' && value <= 0) {
    element.scrollTop = value = 0; // don't allow negative scroll
    element.dispatchEvent(yStartEvent);
  }

  if (axis === 'left' && value <= 0) {
    element.scrollLeft = value = 0; // don't allow negative scroll
    element.dispatchEvent(xStartEvent);
  }

  var i = instances.get(element);

  if (axis === 'top' && value >= i.contentHeight - i.containerHeight) {
    // don't allow scroll past container
    value = i.contentHeight - i.containerHeight;
    if (value - element.scrollTop <= 1) {
      // mitigates rounding errors on non-subpixel scroll values
      value = element.scrollTop;
    } else {
      element.scrollTop = value;
    }
    element.dispatchEvent(yEndEvent);
  }

  if (axis === 'left' && value >= i.contentWidth - i.containerWidth) {
    // don't allow scroll past container
    value = i.contentWidth - i.containerWidth;
    if (value - element.scrollLeft <= 1) {
      // mitigates rounding errors on non-subpixel scroll values
      value = element.scrollLeft;
    } else {
      element.scrollLeft = value;
    }
    element.dispatchEvent(xEndEvent);
  }

  if (!lastTop) {
    lastTop = element.scrollTop;
  }

  if (!lastLeft) {
    lastLeft = element.scrollLeft;
  }

  if (axis === 'top' && value < lastTop) {
    element.dispatchEvent(upEvent);
  }

  if (axis === 'top' && value > lastTop) {
    element.dispatchEvent(downEvent);
  }

  if (axis === 'left' && value < lastLeft) {
    element.dispatchEvent(leftEvent);
  }

  if (axis === 'left' && value > lastLeft) {
    element.dispatchEvent(rightEvent);
  }

  if (axis === 'top') {
    element.scrollTop = lastTop = value;
    element.dispatchEvent(yEvent);
  }

  if (axis === 'left') {
    element.scrollLeft = lastLeft = value;
    element.dispatchEvent(xEvent);
  }

};

},{"./instances":18}],21:[function(require,module,exports){
'use strict';

var _ = require('../lib/helper');
var dom = require('../lib/dom');
var instances = require('./instances');
var updateGeometry = require('./update-geometry');
var updateScroll = require('./update-scroll');

module.exports = function (element) {
  var i = instances.get(element);

  if (!i) {
    return;
  }

  // Recalcuate negative scrollLeft adjustment
  i.negativeScrollAdjustment = i.isNegativeScroll ? element.scrollWidth - element.clientWidth : 0;

  // Recalculate rail margins
  dom.css(i.scrollbarXRail, 'display', 'block');
  dom.css(i.scrollbarYRail, 'display', 'block');
  i.railXMarginWidth = _.toInt(dom.css(i.scrollbarXRail, 'marginLeft')) + _.toInt(dom.css(i.scrollbarXRail, 'marginRight'));
  i.railYMarginHeight = _.toInt(dom.css(i.scrollbarYRail, 'marginTop')) + _.toInt(dom.css(i.scrollbarYRail, 'marginBottom'));

  // Hide scrollbars not to affect scrollWidth and scrollHeight
  dom.css(i.scrollbarXRail, 'display', 'none');
  dom.css(i.scrollbarYRail, 'display', 'none');

  updateGeometry(element);

  // Update top/left scroll to trigger events
  updateScroll(element, 'top', element.scrollTop);
  updateScroll(element, 'left', element.scrollLeft);

  dom.css(i.scrollbarXRail, 'display', '');
  dom.css(i.scrollbarYRail, 'display', '');
};

},{"../lib/dom":3,"../lib/helper":6,"./instances":18,"./update-geometry":19,"./update-scroll":20}]},{},[1]);


