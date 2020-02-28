pp<!DOCTYPE html>
<html xmlns:ng="http://angularjs.org" lang="de">
  <head>
    <meta charset="utf-8"/>
    <meta name="robots" content="noindex,nofollow"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.00"/>
    <meta name="description" content=""/>
    <meta name="author" content=""/>
    <meta name="format-detection" content="telephone=no">
    <link rel="shortcut icon" href="<?= $this->builder_url('/img/'.$this->env_get('preset:scheme:'.$this->us_get('preset_name')).'/favicon.png') ?>" type="image/x-icon"/>
    <title><?= $this->env_get('preset:title:'.$this->us_get('preset_name')) ?></title>
    <link href="<?= $this->builder_css_url() ?>" rel="stylesheet"/>
    <link href="<?= $this->builder_url('/resources/mdb.css') ?>" rel="stylesheet"/>
    <link href="<?= $this->builder_url('/resources/style.css') ?>" rel="stylesheet"/>
    <link href="//netdna.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $this->builder_url('/resources/select2.css') ?>">
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap-theme.min.css">
    <link rel="stylesheet" href="<?= $this->builder_url('/css/tooltipster.bundle.css') ?>">
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="<?= $this->builder_url('/resources/html5shiv.js') ?>"></script>
      <script src="<?= $this->builder_url('/resources/respond.min.js') ?>"></script>
    <![endif]-->
  </head>
  <style type="text/css">
    [ng\:cloak], [ng-cloak], [data-ng-cloak], [x-ng-cloak], .ng-cloak, .x-ng-cloak {
    display: none !important;
    }
  </style>
  <body ng-app="app">
    <div id="wrap" ng-init=" us = {
                        url               : '<?= $this->us_url("/"); ?>',
                        ajaxurl           : '<?= $this->us_url("/get"); ?>',
                        sameurl           : '<?= $this->us_same_url(); ?>',
                        editurl           : '<?= $this->us_url('/edit_profile/'); ?>',
                        builderurl        : '<?= $this->builder_url(); ?>',
                        newTanUrl         : '<?= $this->us_url('/mymobile/newtan'); ?>',
                        msisdn            : '<?= $this->us_get("msisdn"); ?>',
                        mobileID          : '<?= $this->us_get("mobileID"); ?>',
                        resourceUrl       : '<?= $this->env_get("domain:resource_url") ?>',
                        auth              : '<?= $this->us_get("auth"); ?>',
                        tan               : '<?= $this->us_get("tan"); ?>',
                        scheme            : '<?= $this->env_get('preset:scheme:'.$this->us_get('preset_name')) ?>',
                        lang              : '<?= $this->us_get('lang') ?>',
                        sms               : '<?= $this->sms ?? false ?>',
                        dir               : '<?= $this->us_get('preset_name') ?>',
                        title             : '<?= $this->env_get('preset:title:'.$this->us_get('preset_name')) ?>',
                        }">

        <div id="top-block"  ng-controller="appCtrl" ng-cloak style="overflow: scroll;">

            <?php include $include_page; ?>

            <div id="footer"  ng-show="!switchViews.showStats" class="top20 bottom20">
                <a id="google-banner" class="" href="<?= $this->env_get('preset:payment:'.$this->env_get('preset:scheme:'.$this->us_get('preset_name')).'_apk_url') ?>" title="" target="_blank">
                    <div class="center color-white">{{text[260]}}</div><br>
                    <img ng-src="{{us.builderurl+'/img/google-play-banner-de.png'}}" id="" class="center" ng-show="us.lang == 'de'" style="max-width: 200px;">
                    <img ng-src="{{us.builderurl+'/img/google-play-banner-en.png'}}" id="" class="center" ng-hide="us.lang == 'de'" style="max-width: 200px;">
                </a><br><br>
                <div>
                    <p style="margin:0;text-align:center;" class="color-white">&copy;&nbsp;{{us.title}}&nbsp;{{year}}

                        | <a href="" ng-click="userSessionUrl(us.url, 'static/'+us.lang+'/'+text[80])" class="color-white" target="_self">{{text[80]}}</a>
                        | <a href="" ng-click="userSessionUrl(us.url, 'static/'+us.lang+'/'+text[90])" class="color-white" target="_self">{{text[90]}}</a>
                        | <a href="" ng-click="userSessionUrl(us.url, 'static/'+us.lang+'/'+text[100])" class="color-white" target="_self">{{text[100]}}</a>
                    </p>
                </div>
            </div>
        </div>
    </div><!-- /#wrap -->

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.11/angular.min.js"></script>
    <script src="<?= $this->builder_url('/resources/ng-infinite-scroll.min.js') ?>"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/angular-ui-bootstrap/0.12.0/ui-bootstrap-tpls.min.js"></script>
    <script src="<?= $this->builder_url('/resources/perfect-scrollbar.js') ?>"></script>
    <script src="<?= $this->builder_js_url() ?>"></script>
    <script src="<?= $this->builder_url('/resources/mdb.js') ?>"></script>
    <script src="<?= $this->builder_url('/resources/bootbox.min.js') ?>"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.11/angular-sanitize.js"></script>
    <script src="<?= $this->builder_url('/resources/ngBootbox.js') ?>"></script>
    <script src="<?= $this->builder_url('/resources/dropzone.js') ?>"></script>
    <script src="<?= $this->builder_url('/resources/highcharts.js') ?>"></script>
    <script src="<?= $this->builder_url('/resources/ui-select.js') ?>"></script>
    <script src="https://unpkg.com/ionicons@latest/dist/ionicons.js"></script>
    <script src="<?= $this->builder_url('/js/tooltipster.bundle.js') ?>"></script>
  </body>
</html>
