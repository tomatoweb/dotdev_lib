
<!-- Navbar top -->
<div class="navbar" role="navigation" id="navbar-top">
    <div class="container">

        <!-- Logo and title -->
        <div class="navbar-header center tooltipster-bottom" title="{{us.title}}"
            ng-click="switchViews.showProfiles = true;switchViews.showChats = false;switchViews.showChat = false;switchViews.showStats = false;switchViews.showUser = false;"
            data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
            <a id="{{us.scheme}}-logo" class="deco-none" href="">
                <img ng-src="{{us.builderurl + '/img/' + us.scheme + '/favicon.png'}}" id="home-img" alt="{{us.title}}">
                <span class="font40 v-middle color-white">{{us.title}}</span>
            </a>
            <!-- Burger button responsive --><!-- ".in" avoid collapse animation on desktop version -->
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" style="border: 1px solid grey;position: absolute;right: -10px;margin-top: 12px;">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar" style="background-color: grey;"></span>
                <span class="icon-bar" style="background-color: grey;"></span>
                <span class="icon-bar" style="background-color: grey;"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse right40 no-shadow" id="bs-example-navbar-collapse-1" >

            <!-- navbar stats (in top navbar) -->
            <div ng-show="false" id="stats-navbar" class="center" ng-cloak>
                <ul class="nav nav-pills">
                    <li style="min-width:165px; text-align:center;">
                        <a href="" ng-click="
                                    switchStatsViews.showTanUsers = false;
                                    switchStatsViews.showDashboard = true;
                                    switchStatsViews.showWorld = false;
                                    switchStatsViews.showEurope = false;
                                    "
                            style="font-size:16px;border:solid 1px grey;"
                            data-toggle="tab"
                                    >
                        <i class="icon-briefcase icon-2x"></i>Dashboard</a>
                    </li>
                    <li style="min-width:165px; text-align:center;">
                        <a href="" ng-click="
                                    switchStatsViews.showTanUsers = true;
                                    switchStatsViews.showDashboard = false;
                                    switchStatsViews.showWorld = false;
                                    switchStatsViews.showEurope = false;
                                    "
                            style="font-size:16px;border:solid 1px grey;"
                            data-toggle="tab"
                                    >
                        <i class="icon-pencil icon-2x"></i>Tan Users</a>
                    </li>
                    <li class=" in active" style="min-width:165px; text-align:center;">
                        <a href="" ng-click="
                                    switchStatsViews.showTanUsers = false;
                                    switchStatsViews.showDashboard = false;
                                    switchStatsViews.showWorld = true;
                                    switchStatsViews.showEurope = false;
                                    "
                            style="font-size:16px;border:solid 1px grey;"
                            data-toggle="tab"
                                    >
                        <i class="icon-pencil icon-2x"></i>Bragi MOs in der Welt</a>
                    </li>
                    <li style="min-width:165px; text-align:center;">
                        <a href="" ng-click="
                                    switchStatsViews.showTanUsers = false;
                                    switchStatsViews.showDashboard = false;
                                    switchStatsViews.showWorld = false;
                                    switchStatsViews.showEurope = true;
                                    "
                            style="font-size:16px;border:solid 1px grey;"
                            data-toggle="tab"
                                    >
                        <i class="icon-envelope icon-2x"></i>Bragi MOs in Europa</a>
                    </li>
                </ul>
            </div>

            <!-- Buttons login or register -->
            <ul class="navbar-right top8 inline-block padding-left0" ng-show="!us.auth" ng-click="switchViews.showStats = false;">
                <button ng-click="liveChatClicked(profile[0].profileID, profile[0].profileName, us.resourceUrl+'/p'+profile[0].profileID+'/'+profile[0].thumbName);"
                    class="btn btn-{{us.scheme}} grow"
                    data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
                    Login
                </button>
                <button ng-click="liveChatClicked(profile[0].profileID, profile[0].profileName, us.resourceUrl+'/p'+profile[0].profileID+'/'+profile[0].thumbName);"
                    class="btn btn-{{us.scheme}}-sec grow"
                    data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
                    {{text[70]}}
                </button>
            </ul>

            <!-- Icons bar (home, credits, chats, profile) -->
            <ul class="nav navbar-nav navbar-right inline" ng-click="switchViews.showStats = false;">
                <li ng-show="us.auth" class="icons-list">

                    <!-- Home icon -->
                    <div class="no-margin no-padding inline tooltipster-bottom"
                         title="Home"
                         ng-click="switchViews.showProfiles = true;switchViews.showChats = false;switchViews.showChat = false;switchViews.showPics = false;switchViews.showUser = false; doShuffle();">
                        <img class="icons-img" ng-src="{{us.builderurl+'/img/home.png'}}" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
                    </div>

                    <!-- Credits charge icon -->
                    <div ng-show="{{us.sms}}" class="no-margin no-padding inline tooltipster-bottom" data-tooltip-content="#credits" data-toggle="modal" data-target="#paymentForm" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
                         <div class="hidden">
                            <span id="credits">
                                <span class="mediumspringgreen">{{credits.count}}</span> credits<br>
                            </span>
                        </div>
                        <div class="dot-credits"><span>{{credits.count}}</span></div>
                        <img class="icons-img" src="<?= $this->builder_url('/img/flirddy/flirddys.png') ?>">
                    </div>

                    <!-- Chats count and Unread messages count icon -->
                    <div class="inline tooltipster-bottom" data-tooltip-content="#chats-unread"
                         ng-click="
                         checkChats();
                         switchViews.showChats = (chatProfiles.length > 0);
                         switchViews.showProfiles = (chatProfiles.length == 0);
                         switchViews.showChat = false;
                         switchViews.showUser = false;
                         doShuffle();"
                         data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
                        <div class="hidden">
                            <span id="chats-unread">
                                <span class="mediumspringgreen">{{chatProfiles.length}}</span> chats<br>
                                <span class="mediumspringgreen">{{unreadMessages.length}}</span> {{text[180]}}
                            </span>
                        </div>
                        <div class="dot-msgs">
                            <span>{{unreadMessages.length}}</span>
                        </div>
                        <div class="dot-chats">
                            <span>{{chatProfiles.length}}</span>
                        </div>
                        <img class="icons-img" src="<?= $this->builder_url('/img/chat.png') ?>" data-toggle="tooltip" data-placement="bottom" data-html="true" title="">
                    </div>


                    <!-- User profile icon -->
                    <div class="no-margin no-padding inline tooltipster-bottom"
                         title="{{text[270]}}"
                        ng-click="checkUserProfile();
                        switchViews.showUser = !(userProfile | isEmpty);
                        switchViews.showChat = false;
                        switchViews.showChats = false;
                        switchViews.showProfiles = (userProfile | isEmpty);
                        doShuffle();">
                        <img class="icons-img" src="<?= $this->builder_url('/img/profile.png') ?>" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1.in">
                    </div>
                </li>
            </ul>

            <!-- three dots icon and options menu top right -->
            <div class="dots-container dropdown-toggle tooltipster-bottom" title="Options" data-toggle="dropdown" ng-cloak></div>
            <ul class="dropdown-menu dots-menu" data-toggle="tooltip" data-placement="right" data-original-title="Log out">

                <!-- german lang option -->
                <li>
                    <a href="" target="_self" ng-click="userSessionUrl(us.url, '', 'lang=de')" class="color-black font15" ng-hide="us.lang == 'de'">
                        <img ng-src="{{us.builderurl + '/img/flagsbtn/germany.png'}}" style="height:27px;" class="left-2">
                        <div class="inline absolute left6 top4">Deutsch</div>
                    </a>
                </li>

                <!-- english lang option -->
                <li>
                    <a href="" target="_self" ng-click="userSessionUrl(us.url, '', 'lang=en')" class="color-black font15" ng-hide="us.lang == 'en'">
                        <img ng-src="{{us.builderurl + '/img/flagsbtn/united_kingdom.png'}}" style="height:27px;" class="left-2">
                        <div class="inline absolute left6 top4">English</div>
                    </a>
                </li>

                <!-- stats view option button -->
                <li>
                    <a href="" class="color-black font15" ng-show="us.sms" ng-click="
                    switchViews.showProfiles = false;
                    switchViews.showUser = false;
                    switchViews.showChat = false;
                    switchViews.showChats = false;
                    switchViews.showStats = true;
                    tanUsersStats($event);visitsUsersStats($event);">
                    <ion-icon name="ios-stats" class="xxl"></ion-icon>
                    <div class="inline absolute top12 left10">Stats</div>
                    </a>
                </li>

                <!-- log out option -->
                <li>
                    <a href="<?= $this->us_url('/?sesreset=1') ?>" target="_self" class="font15">
                        <ion-icon name="md-log-out" class="xl left5"></ion-icon>
                        <div class="inline absolute left10">Log out</div>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- feedback message container for typical user actions -->
<div id="alert" class="alert"></div>

<!-- Slider top -->
<div class="container" ng-show="!us.auth">
    <div class="row">

        <!-- slider top left -->
        <div ng-click="switchViews.showProfiles = true;switchViews.showChats = false;switchViews.showChat = false;switchViews.showPics = false;switchViews.showUser = false;doShuffle();" id="slider-left" class="col-sm-6 col-xs-12 square no-padding no-margin center cursor-pointer">
            <img ng-show="us.lang == 'de'" class="img-full advert" title="{{us.title}}" ng-src="{{us.builderurl + '/img/slider_'+us.scheme+'.png'}}" style="height:185px;">
            <img ng-show="us.lang != 'de'" class="img-full advert" title="{{us.title}}" ng-src="{{us.builderurl + '/img/slider_en_'+us.scheme+'.png'}}" style="height:185px;">
        </div>

        <!-- slider top right -->
        <div id="slider-right" class="col-sm-6 col-xs-12 square no-padding center">

            <!-- carousel -->
            <div id="myCarousel" class="carousel slide" data-interval="0" data-ride="carousel">

                <!-- Carousel items -->
                <div class="carousel-inner">
                    <div class="item {{::($index === 0 ? 'active' : '')}}" ng-repeat="profile in profiles track by $index" style="height: 205px;">
                        <div class="carousel-caption media-box-big center cursor-pointer"
                             ng-style="{'background-image': thumb==true  ? ' url(' + us.resourceUrl+'/p'+profile.profileID+'/'+ profile.thumbName + ')' : ' url(' + us.resourceUrl+'/p'+profile.profileID+'/'+ profile.imageName + ')'}"
                             ng-mouseover="thumb=false"
                             ng-mouseleave="thumb=true"
                             style="height:267px;border:none;"
                             ng-click="liveChatClicked($index)"; scrollTo('top-anchor')>
                            <div class="media-box-plate">
                                <span>{{profile.profileName}}&nbsp{{profile.age}}</span>
                                <button class="btn button-{{us.scheme}} grow" data-toggle="modal">Chat</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Carousel nav -->
                <a class="carousel-control left" href="#myCarousel" data-slide="prev" style="height:77%;">
                    <span class="glyphicon glyphicon-chevron-left"></span>
                </a>
                <a class="carousel-control right" href="#myCarousel" data-slide="next" style="height:77%;">
                    <span class="glyphicon glyphicon-chevron-right"></span>
                </a>
            </div>
        </div>
    </div>
</div>



<!-- Profiles grid homepage -->
<div ng-show="switchViews.showProfiles" class="" infinite-scroll='loadInfiniteProfiles();' infinite-scroll-container='"#top-block"' infinite-scroll-disabled='endOfData'>
    <div class="border container"><div id="top-anchor"></div>
        <div class="row">

            <!-- One big box -->
            <div class="no-padding border col-sm-6 col-xs-12 big cursor-pointer" ng-click="liveChatClicked(14); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(14)}}" class="14"></div>
                <div ng-style="{{bgImg(14, 'thumb')}}" class="top 14t"></div>
                <div class="media-box-plate ">
                    <span>{{profiles[14].profileName}}&nbsp{{profiles[14].age}}</span>
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>

            <!-- 4 small boxes -->
            <div ng-repeat="i in [13,12,11,10]" class="no-padding border col-sm-3 col-xs-6 small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
                <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
                <div class="media-box-plate ">
                    {{profiles[i].profileName}}
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>
        </div>
        <div class="row">

            <!-- One big box -->
            <div class="no-padding border col-sm-6 col-sm-push-6 big cursor-pointer" ng-click="liveChatClicked(9); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(9)}}" class="9"></div>
                <div ng-style="{{bgImg(9, 'thumb')}}" class="top 9t"></div>
                <div class="media-box-plate ">
                    <span>{{profiles[9].profileName}}&nbsp{{profiles[9].age}}</span>
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>

            <!-- 4 small boxes -->
            <div ng-repeat="i in [8,7,6,5]" class="no-padding border col-sm-3 col-sm-pull-6 col-xs-6 small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
                <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
                <div class="media-box-plate ">
                    {{profiles[i].profileName}}
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>
        </div>
        <div class="row">

            <!-- One big box -->
            <div class="no-padding border col-sm-6 col-xs-12 big cursor-pointer" ng-click="liveChatClicked(4); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(4)}}" class="4"></div>
                <div ng-style="{{bgImg(4, 'thumb')}}" class="top 4t"></div>
                <div class="media-box-plate ">
                    <span>{{profiles[4].profileName}}&nbsp{{profiles[4].age}}</span>
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>

            <!-- 4 small boxes -->
            <div ng-repeat="i in [3,2,1,0]" class="no-padding border col-sm-3 col-xs-6 small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
                <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
                <div class="media-box-plate ">
                    {{profiles[i].profileName}}
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>
        </div>

        <!-- infinite-scroll profiles -->
        <div class="row">
            <div class="no-padding border col-xs-6 col-md-3 col-sm-3 small cursor-pointer"
                 ng-repeat='image in images'
                 ng-click="liveChatClicked(image); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(image)}}" class="{{image}}"></div>
                <div ng-style="{{bgImg(image, 'thumb')}}" class="top {{image}}t"></div>
                <div class="media-box-plate ">
                    {{profiles[image].profileName}}
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>
        </div>
    </div>
    <!-- 'Load more' button -->
    <div ng-hide="endOfData" ng-click="loadMore()" class="top30 bottom20">
        <img ng-src="{{us.builderurl + '/img/load_more_'+us.scheme+'.png'}}" class="grow img-plus center cursor-pointer tooltipster-left" title="{{text[280]}}">
    </div>
</div>

<!-- Modal login -->
<div class="modal fade" id="elegantModalForm" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <!--Content-->
        <div class="modal-content form-elegant">

            <!--Header-->
            <div class="modal-header text-center">
                <img ng-src="{{us.builderurl+'/img/banner_login.png'}}" id="banner-login" alt="<?= $this->env_get('preset:payment:title') ?>">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -256px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <p id="label-handynr" ng-show="!us.msisdn">{{text[110]}} <span style="color:#00b663">{{text[120]}}</span>{{text[130]}}</p>

            <div class="modal-body mx-4">

             <div id="msisdnError" class="center"></div>

                <div ng-show="!us.msisdn">

                    <!-- select prefix  -->
                    <ui-select ng-model="countrySelection.selected" theme="select2" on-select="" id="country-select" class="inline-block color-black padding-right8">
                        <ui-select-match class="ui-select-match font14" placeholder="">
                            {{($select.selected.code | uppercase) + '&nbsp;&nbsp; +' + $select.selected.prefix_int}}
                        </ui-select-match>
                        <ui-select-choices repeat="country in countries | propsFilter: {code: $select.search}" class="color-black">
                            <small><span  ng-bind-html="country.code | uppercase | highlight: $select.search" class="font-light font14"></span></small>
                            <small><span ng-bind-html="'+'+country.prefix_int | highlight: $select.search" class="font-light font14"></span></small>
                        </ui-select-choices>
                    </ui-select>

                    <!-- MSISDN input  -->
                    <div class="md-form mb-5 inline-block">
                        <input type="number" id="msisdn" class="form-control" ng-model="msisdnFilled" ng-value="msisdnFilled">
                        <label class="font14 top-8 active" data-error="wrong" data-success="right" for="msisdn" style="top: 1.5rem;">{{text[140]}}</label>
                    </div>

                    <!-- examples MSISDN
                    <div class="row">
                        <div class="col-xs-1 smaller">{{text[145]}}</div>
                        <div class="col-xs-11">
                            <i><span class="smaller col-xs-12 col-sm-4 no-padding"><img ng-src="{{us.builderurl+'/img/flagsbtn/germany.png'}}" height="20" width="26" alt="Deutschland">&nbsp;491601234567</span></i>
                            <i><span class="smaller col-xs-12 col-sm-4 no-padding"><img ng-src="{{us.builderurl+'/img/flagsbtn/united_kingdom.png'}}" height="20" width="26" alt="UK">&nbsp;441601234567</span></i>
                            <i><span class="smaller col-xs-12 col-sm-4 no-padding"><img ng-src="{{us.builderurl+'/img/flagsbtn/austria.png'}}" height="20" width="26" alt="Austria">&nbsp;431601234567</span></i>
                        </div>
                    </div -->

                    <!-- Button submit -->
                    <div class="row">
                        <button ng-show="msisdnFilled" ng-click="validateMsisdn($event);" type="button" class="btn btn-{{us.scheme}} top20 center-flat">{{text[150]}}</button>
                    </div>

                </div>

                <!-- TAN input  -->
                <div class="md-form pb-3" ng-show="us.msisdn">
                    <div id="tanError" class=""></div>
                    <input type="text" id="tan" class="form-control" ng-value="us.tan">
                    <label class="font14" data-error="wrong" data-success="right" for="tan">Dein persönlicher TAN</label>
                </div>

                <div class="text-center mb-3" ng-show="us.msisdn">
                    <button ng-click="validateTan($event);" type="button" class="btn btn-{{us.scheme}} center-flat top20">Einloggen</button>
                </div>
            </div>

            <!--Footer-->
            <div class="modal-footer mx-5 pt-3 mb-1">
                <p class="font-small grey-text d-flex justify-content-end"> <a href="#" class="blue-text ml-1"> </a></p>
                 <img ng-src="{{us.builderurl+'/img/privacy-data-protection.png'}}" class="absolute right" alt="" style="width:130px;margin: -59px -91px;">
            </div>
        </div>
        <!--/.Content-->
    </div>
</div>

<!-- Chats view -->
<div ng-show="switchViews.showChats && us.auth" class="container border">
    <div class="row no-gutters">

        <!-- Chats list -->
        <perfect-scrollbar class="col-sm-9 border no-padding no-margin height960" wheel-propagation="false" wheel-speed="10" min-scrollbar-length="20" id="top50users">

            <div ng-repeat="profile in chatProfiles"
                 class="col-sm-12 chat-list-item border-black no-padding"
                 ng-click="getChat(profile.profileID, profile.profileName, profile.imageName, profile.thumbName, $event); switchViews.showChat = true; switchViews.showChats = false;">

                <div class="row no-margin no-padding">
                    <div class="col-xs-2 no-margin no-padding">
                        <img ng-src="{{us.resourceUrl+'/p'+profile.profileID+'/'+ profile.thumbName}}" class="img-responsive img100 padding5"/>
                    </div>

                    <div class="col-xs-10 no-margin no-padding">
                        <div>{{profile.profileName}}</div>
                        <div class="font-light">{{profile.lastMT}}
                            <div class="float-right font-light font-small" >{{profile.lastMTCreateTime | datetime}}&nbsp</div>
                        </div>
                    </div>
                </div>

            </div>
        </perfect-scrollbar>

        <!-- 6 small boxes vertical -->
        <perfect-scrollbar class="col-sm-3 no-padding no-margin height960">
            <div ng-repeat="i in [0,1,2,3,4,5]" class="no-padding border col-sm-12 col-xs-6 small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
                <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
                <div class="media-box-plate ">
                    {{profiles[i].profileName}}
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>
        </perfect-scrollbar>
    </div>
</div>

<!-- Single Chat view -->
<div ng-show="switchViews.showChat && us.auth" class="container border dark">
    <div class="row">

        <!-- Big box : fake profile image and gallery -->
        <div class="col-sm-6 col-xs-12 no-padding no-margin">
            <div class="col-sm-12 big-no-child border no-padding no-margin black">

                <!-- background image -->
                <div ng-style="{'background-image': ' url(' + (selectedImage ? us.resourceUrl+'/p'+profileID+'/'+selectedImage.name : us.resourceUrl+'/p'+profileID+'/'+profileImageName) + ')'}"
                     class="img-cover cursor-pointer"
                     data-toggle="modal" data-target=".bd-example-modal-lg"
                     ng-click="!$parent.selectedImage ? (!$parent.selectedImage = imgs[0])"
                     style="height:300px">
                </div>

                <!-- modal image -->
                <div class="modal fade bd-example-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg resp-modal">
                        <div class="close-btn" data-dismiss="modal"></div>

                        <div class="modal-content" style="background-color:transparent">

                            <!-- carousel -->
                            <div id="profile-carousel" class="carousel slide" data-interval="0" data-ride="carousel">

                                <!-- Carousel items -->
                                <div class="carousel-inner">

                                    <div class="item {{(image.imageID == selectedImage.imageID || ($index == 0 && !selectedImage)) ? 'active' : ''}}" ng-repeat="image in imgs track by $index">

                                        <img ng-src="{{us.resourceUrl+'/p'+profileID+'/'+image.name}}" class="img-responsive-full" />

                                    </div>

                                </div>

                                <!-- Carousel nav -->
                                <a class="carousel-control left" href="#profile-carousel" data-slide="prev">
                                    <span class="glyphicon glyphicon-chevron-left top250"></span>
                                </a>
                                <a class="carousel-control right" href="#profile-carousel" data-slide="next">
                                    <span class="glyphicon glyphicon-chevron-right top250"></span>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- gallery -->
                <div class="media-box-plate" style="white-space: nowrap;">
                    <div class="row no-margin {{us.scheme}}-scrollbar" id="profile-gallery" >
                        <span ng-repeat="image in imgs" class="no-gutters cursor-pointer" ng-click="$parent.selectedImage = image">
                            <img ng-show="image.moderator == 0" style="height:60px;" ng-src="{{us.resourceUrl+'/p'+image.profileID+'/'+image.name}}"/>
                        </span>
                    </div>
                </div>
            </div>

            <!-- 2 small boxes -->
            <div ng-repeat="i in [0,1]" class="no-padding border col-sm-6 hidden-xs small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
                <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
                <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
                <div class="media-box-plate ">
                    {{profiles[i].profileName}}
                    <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
                </div>
            </div>
        </div>

        <!-- messages box -->
        <div class="col-sm-6 col-xs-12 border  no-padding no-margin chat-msg">

            <!-- Profile name and details icon -->
            <div class="row no-margin no-padding" id="chat-profile-name">
                <div id="profile-name">&nbsp{{profileName}}</div>
                <img ng-show="chatProfiles.length > 0" class="details cursor-pointer" src="<?= $this->builder_url('/img/details.png') ?>" ng-click="getChats();switchViews.showChats = true; switchViews.showChat = false; doShuffle();"
                     data-toggle="tooltip" data-placement="right" data-original-title="Chats">
            </div>

            <!-- messages list -->
            <perfect-scrollbar class="col-12 col-sm-12 col-md-12" wheel-propagation="false" wheel-speed="5" min-scrollbar-length="20" id="chat-table" style="" my-main-directive>
                    <div style="padding:0px 20px;" ng-repeat="message in chat" class="col-sm-12 msg" my-repeat-directive>
                        <div ng-if="message.from == 2" class="box-left-title"><br>
                            <div class="font-light font-small">{{message.createTime | datetime}}</div>
                            <div class="bubble-she-{{us.scheme}}">
                                <div class="">
                                    <img ng-cloak ng-src="{{us.resourceUrl+'/p'+profileID+'/'+profileThumb}}" class="img-responsive thumb-profile"/>
                                </div>
                                <div class="chat-text-left font-light">
                                    {{message.text}}
                                </div>
                            </div>
                        </div>
                        <div ng-if="message.from == 1" class="box-right-title"><br>
                            <div class="font-light font-small">{{message.createTime | datetime}}</div>
                            <div class="bubble-he-{{us.scheme}}">
                                {{message.text}}
                            </div>
                        </div>
                    </div>
            </perfect-scrollbar>

            <!-- submit MO -->
            <form ng-show="{{us.sms}}"  class="row margin5" ng-submit="sendMO();">
                <div class="col-xs-10 no-padding">
                    <div id="sentError" class=""></div>
                    <input type="text" id="mo" class="form-control font-light" value="" ng-click="checkCredits();">
                </div>
                <div class="col-xs-2 green-{{us.scheme}} sent" >
                    <input type="submit" id="sendMO" class="green-{{us.scheme}} sent" value="" style="background-image:url(<?= $this->builder_url('/img/Sent.png') ?>);" />
                </div>
            </form>
        </div>

        <!-- 2 small boxes -->
        <div ng-repeat="i in [2,3]" class="no-padding border col-sm-3 hidden-xs small hidden-opt cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
            <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
            <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
            <div class="media-box-plate ">
                {{profiles[i].profileName}}
                <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
            </div>
        </div>
    </div>

    <!-- 8 small boxes -->
    <div class="row">
        <div ng-repeat="i in [4,5,6,7,8,9,10,11]" class="no-padding border col-sm-3 col-xs-6 small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor'); scrollTo('top-anchor')">
            <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
            <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
            <div class="media-box-plate ">
                {{profiles[i].profileName}}
                <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
            </div>
        </div>

    </div>
</div>

<!-- User profile view -->
<div ng-show="switchViews.showUser && us.auth" class="container border dark">
    <div class="row">

        <!-- User profile image and gallery -->
        <div class="col-sm-6 col-xs-12 no-padding no-margin">
            <div class="col-sm-12 big-no-child border no-padding no-margin black" ng-mouseover="showBurgerBtn=true" ng-mouseleave="showBurgerBtn=true" ng-init="showBurgerBtn=true">

                <!-- background image -->
                <div ng-style="{'background-image': ' url(' + (selectedImg ? us.resourceUrl+'/p'+selectedImg.profileID+'/'+selectedImg.name : (userProfile.imageName ? us.resourceUrl+'/p'+userProfile.profileID+'/'+userProfile.imageName : us.builderurl + '/img/ico_man.svg')) + ')'}"
                     class="img-cover cursor-pointer"
                     data-toggle="modal" data-target=".bd-modal-lg"
                     style="height:300px">

                    <!-- burger button -->
                    <button ng-show="showBurgerBtn && selectedImg" type="button" class="burger-btn dropdown-toggle navbar-toggle left5 absolute" data-toggle="dropdown">
                        <span class="icon-bar lightgrey"></span>
                        <span class="icon-bar lightgrey"></span>
                        <span class="icon-bar lightgrey"></span>
                    </button>

                    <!-- image drop menu -->
                    <ul class="dropdown-menu top40 left5 flirddy" ng-show="showBurgerBtn">
                        <li onclick="return confirm('Please confirm you want to delete this picture')">
                            <a href="" ng-click="updateUserImg('delete_image', userProfile.profileID, selectedImg.imageID); refreshUserProfile();" class="color-black">{{text[20]}}</a>
                        </li>
                        <li>
                            <a href="" ng-click="updateUserImg('highlight_image', userProfile.profileID, selectedImg.imageID); refreshUserProfile();" class="color-black">{{text[30]}}</a>
                        </li>
                    </ul>
                </div>

                <!-- modal image -->
                <div class="modal fade bd-modal-lg" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg fit-content">
                        <div class="close-btn" data-dismiss="modal"></div>
                        <img ng-src="{{selectedImg ? us.resourceUrl+'/p'+selectedImg.profileID+'/'+selectedImg.name : (userProfile.imageName ? us.resourceUrl+'/p'+userProfile.profileID+'/'+userProfile.imageName : us.builderurl + '/img/ico_man.svg')}}" class="modal-content"/>
                    </div>
                </div>

                <!-- gallery -->
                <div class="media-box-plate" style="white-space: nowrap;">

                    <!-- dropzone for user images upload -->
                    <form action="<?= $this->us_url('/edit_profile/') ?>" method="POST" enctype="multipart/form-data" ng-submit="" id="user-images-form">
                        <div class="dropzone" id="userDropzone" ng-show="showDropzone">
                            <input type="hidden" id="profileID" name="profileID" class="form-control" value="{{userProfile.profileID}}">
                            <input type="hidden" id="ajaxurl" class="form-control" value="<?= $this->us_url('/edit_profile/') ?>">
                            <input type="hidden" id="user-imgs-length" name="user-imgs-length" value="{{userProfile.images.length}}" ng-cloak>
                        </div>
                        <button type="submit" id="save-changes" class="btn button-{{us.scheme}}" ng-show="showDropzone" ng-click="refreshUserProfile();">Speichern</button>
                    </form>

                    <!-- images gallery -->
                    <div class="row no-margin {{us.scheme}}-scrollbar" id="profile-gallery">
                        <span ng-repeat="image in userProfile.images" class="no-gutters cursor-pointer" ng-click="$parent.selectedImg = image">
                            <img ng-cloak style="height:60px;" ng-src="{{us.resourceUrl+'/p'+image.profileID+'/'+image.name}}"/>
                        </span>

                        <!-- icon + -->
                        <img ng-src="{{us.builderurl+'/img/plus.png'}}" class="plus cursor-pointer" ng-click="showDropzone = !showDropzone" ng-hide="!userProfile || userProfile.images.length > 4">
                    </div>
                </div>
            </div>
        </div>

        <!-- user profile informations -->
        <div class="col-sm-6 col-xs-12 no-padding no-margin big-no-child">
            <form id="user-form" ng-submit="">

                <!-- user name and description -->
                <input type="text" name="name-input" class="user-input xxl light-dark" id="name-input" value="{{userProfile.profileName}}" ng-blur="updateUserProfile('name', 'name-input')" placeholder="{{text[60]}}">
                <img class="pencil-top cursor-pointer" ng-click="giveFocus('name-input')" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                <div class="media-box-plate-long">

                    <!-- user description -->
                    <textarea class="no-margin no-padding font-light font-tiny form-control rounded-0" rows="2" id="user-descr-area" ng-show="showDescrArea" ng-blur="showDescrArea=false;updateUserProfile('description', 'user-descr-area');">{{userProfile.description}}</textarea>
                    <div class="no-margin no-padding" id="user-profile-name" ng-show="!showDescrArea">
                        <div id="user-descr" class="font-light black">
                            &nbsp{{userProfile.description ? userProfile.description : text[10]}}
                            <img class="pencil-descr pencil cursor-pointer" ng-click="giveFocus('user-descr-area');showDescrArea = true;" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                        </div>
                    </div>

                    <!-- age, height, weight, gender, orientation, plz -->
                    <div class="col-12 col-sm-12 col-md-12 light-dark">
                        <div class="row">
                            <div class="col-xs-4 no-padding no-margin" ng-mouseover="changeAgeImage=true" ng-mouseleave="changeAgeImage=false" ng-init="changeAgeImage=false">
                                <input type="text" name="age-input" class="user-input xxl light-dark blur-on-enter" id="age-input" value="{{userProfile.age ? userProfile.age : 20}}" ng-blur="updateUserProfile('age', 'age-input')">
                                <img class="width95" ng-show="changeAgeImage" ng-src="<?= $this->builder_url('/img/birthday_on.png') ?>" />
                                <img class="width95" ng-hide="changeAgeImage" ng-src="<?= $this->builder_url('/img/birthday_off.png') ?>" />
                                <img class="pencil cursor-pointer" ng-click="giveFocus('age-input')" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                            </div>
                            <div class="col-xs-4 no-padding no-margin" ng-mouseover="changeHeightImage=true" ng-mouseleave="changeHeightImage=false" ng-init="changeHeightImage=false">
                                <input type="text" name="height-input" class="user-input xxl light-dark blur-on-enter" id="height-input" value="{{userProfile.height ? userProfile.height : 170}}" ng-blur="updateUserProfile('height', 'height-input')">
                                <img class="width95" ng-show="changeHeightImage" ng-src="<?= $this->builder_url('/img/height_on.png') ?>" />
                                <img class="width95" ng-hide="changeHeightImage" ng-src="<?= $this->builder_url('/img/height_off.png') ?>" />
                                <img class="pencil cursor-pointer" ng-click="giveFocus('height-input')" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                            </div>
                            <div class="col-xs-4 no-padding no-margin" ng-mouseover="changeWeightImage=true" ng-mouseleave="changeWeightImage=false" ng-init="changeWeightImage=false">
                                <input type="text" name="weight-input" class="user-input xxl light-dark blur-on-enter" id="weight-input" value="{{userProfile.weight ? userProfile.weight : 70}}" ng-blur="updateUserProfile('weight', 'weight-input')">
                                <img class="width95" ng-show="changeWeightImage" ng-src="<?= $this->builder_url('/img/scale_on.png') ?>" />
                                <img class="width95" ng-hide="changeWeightImage" ng-src="<?= $this->builder_url('/img/scale_off.png') ?>" />
                                <img class="pencil cursor-pointer" ng-click="giveFocus('weight-input')" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                            </div>
                            <div class="col-xs-4 no-padding no-margin" ng-mouseover="changeGenderImage=true" ng-mouseleave="changeGenderImage=false" ng-init="changeGenderImage=false">
                                <select  class="form-control user-select"  name="gender-input" id="gender-select" ng-model="gender.selected" ng-show="showGenderSelect" ng-change="updateUserProfile('gender', 'gender-select'); showGenderSelect = false" ng-blur="showGenderSelect=false">
                                    <option ng-repeat="g in gender" value="{{g.gender}}"  ng-selected="g.gender == (userProfile.gender | lowercase)">{{g.gender}}</option>
                                </select>
                                <div type="text" name="gender-input" class="user-input xxl light-dark" id="gender-input">{{(userProfile.gender | lowercase) == 'f' ? 'Frau' : 'Mann'}}</div>
                                <img class="width95" ng-show="changeGenderImage" ng-src="<?= $this->builder_url('/img/gender_on.png') ?>" />
                                <img class="width95" ng-hide="changeGenderImage" ng-src="<?= $this->builder_url('/img/gender_off.png') ?>" />
                                <img class="pencil cursor-pointer" ng-click="showGenderSelect=true" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                            </div>
                            <div class="col-xs-4 no-padding no-margin" ng-mouseover="changeOrientationImage=true" ng-mouseleave="changeOrientationImage=false" ng-init="changeOrientationImage=false">
                                <select  class="form-control user-select"  name="orientation-input" id="orientation-select" ng-model="orientation.selected" ng-show="showOrientationSelect" ng-change="updateUserProfile('orientation', 'orientation-select'); showOrientationSelect = false" ng-blur="showOrientationSelect=false">
                                    <option ng-repeat="o in orientation" value="{{o.orientation}}"  ng-selected="o.orientation == (userProfile.orientation | lowercase)">{{o.orientation}}</option>
                                </select>
                                <div type="text" class="user-input xxl light-dark" id="orientation-input" ng-show="(userProfile.orientation | lowercase) == 'f' || !userProfile.orientation">Frau</div>
                                <div type="text" class="user-input xxl light-dark" id="orientation-input" ng-show="(userProfile.orientation | lowercase) == 'm'">Mann</div>
                                <div type="text" class="user-input xxl light-dark" id="orientation-input" ng-show="(userProfile.orientation | lowercase) == 'b'">Bi</div>
                                <img class="width95" ng-show="changeOrientationImage" ng-src="<?= $this->builder_url('/img/search_on.png') ?>" />
                                <img class="width95" ng-hide="changeOrientationImage" ng-src="<?= $this->builder_url('/img/search_off.png') ?>" />
                                <img class="pencil cursor-pointer" ng-click="showOrientationSelect=true" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                            </div>
                            <div class="col-xs-4 no-padding no-margin" ng-mouseover="changePlzImage=true" ng-mouseleave="changePlzImage=false" ng-init="changePlzImage=false">
                                <input type="text" name="plz-input" class="user-input xxl light-dark blur-on-enter" id="plz-input" value="{{userProfile.plz ? userProfile.plz : 12345}}" ng-blur="updateUserProfile('plz', 'plz-input')">
                                <img class="width95" ng-show="changePlzImage" ng-src="<?= $this->builder_url('/img/city_on.png') ?>" />
                                <img class="width95" ng-hide="changePlzImage" ng-src="<?= $this->builder_url('/img/city_off.png') ?>" />
                                <img class="pencil cursor-pointer" ng-click="giveFocus('plz-input')" ng-src="<?= $this->builder_url('/img/Pencil_26px.png') ?>" />
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- 8 small boxes -->
    <div class="row">
        <div ng-repeat="i in [0,1,2,3,4,5,6,7]" class="no-padding border col-sm-3 col-xs-6 small cursor-pointer" ng-click="liveChatClicked(i); scrollTo('top-anchor')">
            <div ng-style="{{bgImg(i)}}" class="{{i}}"></div>
            <div ng-style="{{bgImg(i, 'thumb')}}" class="top {{i}}t"></div>
            <div class="media-box-plate ">
                {{profiles[i].profileName}}
                <button class="btn button-{{us.scheme}}" data-toggle="modal">Chat</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal payment -->
<div class="modal fade" id="paymentForm" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">

        <!--Content-->
        <div class="modal-content form-elegant grey-{{us.scheme}}">

            <!--Header-->
            <div class="modal-header text-center grey-{{us.scheme}}">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -256px;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!--Body-->
            <div class="modal-body mx-4 grey-{{us.scheme}} payment">

                <img ng-click="charge(e);" src="<?= $this->builder_url('/img/payment.png') ?>" alt="<?= $this->env_get('preset:payment:title') ?>" style="width: 100%;">

            </div>

        </div>
        <!--/.Content-->
    </div>
</div>

<!-- Stats view -->
<div ng-show="switchViews.showStats" id="stats-container">

    <!-- TAN Users -->
    <div ng-show="switchStatsViews.showTanUsers" class="row top30">


        <h4 class="center color-white"><b>Tan &nbsp;&nbsp; Login</b></h4>

        <!-- TAN Charts -->
        <div id="tan-charts"></div>

        <!-- DatePicker -->
        <div class="col-sm-2" ng-show="false">
            <div>
                Von&nbsp<md-datepicker ng-model = "dateHeavyUsers.from" md-placeholder = "Start"></md-datepicker>
            </div>
        </div>
        <div class="col-sm-2" style="display:inline;" ng-show="false">
            Bis&nbsp&nbsp<md-datepicker ng-model = "dateHeavyUsers.to" md-placeholder = "End"></md-datepicker>
        </div>
        <button class="btn-search" type="submit" ng-click="heavyUsersStats();" style="display:inline;" ng-show="false">
            <svg width="15px" height="15px">
                <path d="M11.618 9.897l4.224 4.212c.092.09.1.23.02.312l-1.464 1.46c-.08.08-.222.072-.314-.02L9.868 11.66M6.486 10.9c-2.42 0-4.38-1.955-4.38-4.367 0-2.413 1.96-4.37 4.38-4.37s4.38 1.957 4.38 4.37c0 2.412-1.96 4.368-4.38 4.368m0-10.834C2.904.066 0 2.96 0 6.533 0 10.105 2.904 13 6.486 13s6.487-2.895 6.487-6.467c0-3.572-2.905-6.467-6.487-6.467 "></path>
            </svg>
        </button>

        <h4 class="center color-white"><b>Visitors</b></h4>

        <!-- Visitors Charts -->
        <div id="visits-charts"></div>
    </div>
</div>







