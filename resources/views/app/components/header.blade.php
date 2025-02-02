<?php
use App\Models\StarPortraits;

$starPortraits = StarPortraits::where("status", "=", "Active")->get()->toArray();

if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $agent = $_SERVER['HTTP_USER_AGENT'];
}
?>
<header>
    <div class="header-line">
        <div class="container">
            <div class="row">
                <div class="header-box">
                    <div class="col-md-6">
                        <div class="logo">
                            <a href="{{route('app-main')}}">
                              <img class="img-fluid" src="/assets/app/images/logo.png" alt="Logo" title="Story Star Logo">
                                <!--<img src="{{app_assets("images/logo.png")}}"  class="img-fluid"  alt="Logo"/>-->
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">





                        <?php

                        //if (strlen(strstr($agent, 'Firefox')) > 0) {
                        ?>


                            <!--div class="mail-text text-xs-right">
                                <div id="carouselExampleSlidesOnly" class="carousel slide" data-ride="carousel">
                                    <div class="carousel-inner" role="listbox">
                                        @if($starPortraits)
                                            @foreach($starPortraits as $k=>$star)
                                                <div class="carousel-item {{$k == 0?'active':''}}"
                                                     title="<?= isset($star['title']) ? $star['title'] : '';?>">
                                                    <ul class="start-slider-img">
                                                        <li class="star_mask1">
                                                            <div class="star_clip1">
                                                                <img src="{{storage_url("thumb_".$star['left_image'],'stars')}}"
                                                                     class="img-fluid"/>
                                                            </div>
                                                        </li>
                                                        <li class="star_mask2">
                                                            <div class="star_clip2">

                                                                <img src="{{storage_url("thumb_".$star['center_image'],'stars')}}"
                                                                     class="img-fluid"/>
                                                            </div>
                                                        </li>
                                                        <li class="star_mask3">
                                                            <div class="star_clip3">

                                                                <img src="{{storage_url("thumb_".$star['right_image'],'stars')}}"
                                                                     class="img-fluid"/>
                                                            </div>

                                                        </li>
                                                    </ul>
                                                </div>
                                            @endforeach
                                        @endif

                                    </div>
                                </div>
                            </div-->



                        <?php
                        //}
                        //else{
                        ?>

                        <div class="mail-text text-xs-right{{Request::path()!='home'?(Request::path()!='/'?' mobile-hidden':''):''}}">

                            <div id="carouselExampleSlidesOnly" class="carousel slide" data-ride="carousel">
                                <div class="carousel-inner" role="listbox">
                                    @if($starPortraits)
                                        @foreach($starPortraits as $k=>$star)
                                            <div class="carousel-item {{$k == 0?'active':''}}"
                                                 title="<?= isset($star['title']) ? $star['title'] : '';?>">


                                                <ul class="new_star">
                                                    <li class="star_mask1">
                                                        <svg width="100%" height="100%" baseProfile="full"
                                                             version="1.2">
                                                            <defs>
                                                                <mask id="svgmask1<?=$k?>" maskUnits="userSpaceOnUse"
                                                                      maskContentUnits="userSpaceOnUse"
                                                                      transform="scale(1)">
                                                                    <image width="100%" height="100%"
                                                                           href="{{app_assets("images/left-star-clip.png")}}"/>
                                                                </mask>
                                                            </defs>
                                                            <image mask="url(#svgmask1<?=$k?>)" width="100%"
                                                                   height="100%"
                                                                   y="12" x="5"
                                                                   href="{{storage_url("thumb_".$star['left_image'],'stars')}}"/>
                                                        </svg>
                                                    </li>
                                                    <li class="star_mask2">
                                                        <svg width="100%" height="100%" baseProfile="full"
                                                             version="1.2">
                                                            <defs>
                                                                <mask id="svgmask2<?=$k?>" maskUnits="userSpaceOnUse"
                                                                      maskContentUnits="userSpaceOnUse"
                                                                      transform="scale(1)">
                                                                    <image width="100%" height="100%"
                                                                           href="{{app_assets("images/centert-star-clip.png")}}"/>
                                                                </mask>
                                                            </defs>
                                                            <image mask="url(#svgmask2<?=$k?>)" width="100%"
                                                                   height="100%"
                                                                   y="12" x="5"
                                                                   href="{{storage_url("thumb_".$star['center_image'],'stars')}}"/>
                                                        </svg>
                                                    </li>
                                                    <li class="star_mask3">
                                                        <svg width="100%" height="100%" baseProfile="full"
                                                             version="1.2">
                                                            <defs>
                                                                <mask id="svgmask3<?=$k?>" maskUnits="userSpaceOnUse"
                                                                      maskContentUnits="userSpaceOnUse"
                                                                      transform="scale(1)">
                                                                    <image width="100%" height="100%"
                                                                           href="{{app_assets("images/right-star-clip.png")}}"/>
                                                                </mask>
                                                            </defs>
                                                            <image mask="url(#svgmask3<?=$k?>)" width="100%"
                                                                   height="100%"
                                                                   y="10" x="2"
                                                                   href="{{storage_url("thumb_".$star['right_image'],'stars')}}"/>
                                                        </svg>
                                                    </li>
                                                </ul>


                                            </div>
                                        @endforeach
                                    @endif

                                </div>
                            </div>
                        </div>


                        <?php
                        //}

                        ?>


                    </div>
                </div>
                <div class="nav-boxes">
                    <div class="nav-boxes-inner">
                        <nav class="nav-main">
                            <ul id="menutoggle" class="nav-menu">
                                <li>
                                    <a href="{{route("app-main")}}">
                                        Home
                                    </a>
                                    <div id="sub-menu" class="home-submenu">
                                        <i class="fa fa-angle-down mobile-down-arrow"
                                           aria-hidden="true"></i>
                                    </div>

                                    <ul id="sub-menutoggle" class="submenu" style="left: -1px;
    margin-top: 21px;
    border: solid 1px white;
    border-top: 0;border-top: white 1px solid;">
                                        <li>
                                            <a href="{{route("app-main")}}#star-of-week">Story STARS of the Week</a>
                                        </li>
                                        <li>
                                            <a href="{{route("app-main")}}#author-of-month">Author of the
                                                Month
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{route("app-main")}}#themes">
                                                Story Themes
                                            </a>
                                        </li>
                                        <li>
                                            <?php
                                            $classicsTheme = getClassicLink();
                                            ?>
                                            <a href="{{route('app-story-theme',['theme_id'=>$classicsTheme['theme_id'],'theme_slug'=>$classicsTheme['theme_slug']])}}">
                                                Read classic short stories
                                            </a>
                                        </li>
                                        <li>

                                            <?php
                                            $novelSubject = getNovelsLink();
                                            ?>
                                            <a href="{{route('app-story-subject', ['theme_id'=>$novelSubject['subject_id'],'theme_slug'=>str_slug($novelSubject['subject_title'])])}}">
                                                <!--Longer Stories (Novels/Series/Serials)-->
                                                Read Novels
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{route("app-brightest")}}">Brightest Stars Anthology</a>
                                        </li>
                                    </ul>


                                </li>
                                <li class="{{Route::currentRouteName() == "app-publish-story"?"active":""}}">
                                    <a href="{{route("app-publish-story")}}">Publish Story</a>
                                </li>
                                <li class="{{Route::currentRouteName() == "app-stories"?"active":""}}">

                                    <a href="{{route("app-stories")}}">Read Stories</a>
                                </li>

                                <li class="{{Route::currentRouteName() == "app-contests"?"active":""}}">
                                    <a class="" href="{{route("app-contests")}}">Contests</a>
                                </li>
                                 <li class="{{\Request::is('blog*')?"active":""}}">
                                    <a class="" href="{{route("app-blog")}}">Blog</a>
                                </li>



                                @if(Auth::user())
                                    <li class="mobile-visible">
                                        <a href="{{route("app-account")}}">Account Settings</a>
                                    </li>
                                    <li class="mobile-visible">
                                        <a href="{{route('app-logout')}}">
                                            Logout
                                        </a>

                                    </li>
                                @else

                                    <li class="mobile-visible">
                                        <a href="{{route("login")}}">
                                            LOGIN
                                        </a>
                                        <a href="javascript:void(0)">/</a>
                                        <a href="{{route("register")}}"> SIGN UP</a>
                                    </li>
                                @endif

                            </ul>
                            <ul class="social-media-box">
                                <li>
                                    <a href="{{facebook_url}}" target="_blank">
                                        <img src="{{app_assets("images/facebook.png")}}" alt="Facebook" title="Facebook">
                                    </a>
                                </li>
                                <li>
                                    <a href="{{twitter_url}}" target="_blank">
                                        <img src="{{app_assets("images/twitter.png")}}" alt="Twitter" title="Twitter">
                                    </a>
                                </li>
                                {{--<li>--}}
                                {{--<a href="{{google_url}}" target="_blank">--}}
                                {{--<img src="{{app_assets("images/google-plus.png")}}" alt="Google plus">--}}
                                {{--</a>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<a href="{{instagram_url}}" target="_blank">--}}
                                {{--<img src="{{app_assets("images/instagram.png")}}" alt="Instagram">--}}
                                {{--</a>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<a href="{{linkend_url}}" target="_blank">--}}
                                {{--<img src="{{app_assets("images/linkend.png")}}" alt="Linkedin">--}}
                                {{--</a>--}}
                                {{--</li>--}}
                            </ul>

                            @if(Auth::user())

                                <span class="login-signup-box mobile-hidden logged-inn home-submenu">
                                    <a class="" href="{{route("app-account")}}" style="color: #f4e21e;">
                                        <?php
                                        //$name = explode(" ", Auth::user()->name)[0];
                                        ?>
                                        {{ucwords(Auth::user()->name)}}

                                        <div id="sub-menu" class="home-submenu" style="padding: 0 10px;">
                                            <i class="fa fa-angle-down mobile-down-arrow" style="color: #f4e21e;"
                                               aria-hidden="true"></i>


                                        </div>


                                          </a>
                                            <ul id="sub-menutoggle" class="submenu">

                                                <li>
                                                    <a href="{{route('app-account')}}">My Profile</a>
                                                </li>

                                                <li>
                                                    <a href="{{route('app-fav-authors')}}">Favorite Authors</a>
                                                </li>

                                                <li>
                                                    <a href="{{route('app-fav-stories')}}">Favorite Stories</a>
                                                </li>

                                                <li>
                                                    <a href="{{route('app-logout')}}">Logout</a>
                                                </li>

                                            </ul>
                            </span>


                            @else
                                <span class="login-signup-box mobile-hidden logged-outt">

                            <a class="" href="{{route("login")}}">LOGIN </a> <a
                                            href="javascript:void(0)">/</a> <a
                                            href="{{route("register")}}"> SIGN UP</a>
                            </span>
                            @endif


                            <button id="menu" class="navbar-toggler hidden-sm-up5 custom_btn_nav" type="button"><i
                                        class="fa fa-bars"></i></button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
