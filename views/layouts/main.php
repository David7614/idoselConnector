<?php

/* @var $this \yii\web\View */
/* @var $content string */

use app\assets\AppAsset;
use app\models\User;
use app\modules\IAI\Application\Config;
use yii\bootstrap\Nav;
use yii\helpers\Html;

AppAsset::register($this);
$userType = null;
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?php echo Yii::$app->language ?>">

<head>
    <meta charset="<?php echo Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://fonts.googleapis.com/css?family=Inter:400,600&display=swap" rel="stylesheet">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?php echo Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <?php if (Yii::$app->user->getIdentity() === null): ?>
    <?php else: ?>
        <?php
            $user              = User::findIdentity(Yii::$app->user->id);
			$userType = $user->user_type;
            $applicationConfig = new Config($user);
            echo '<link rel="stylesheet" href=' . ((new \app\modules\IAI\Application\Resources($applicationConfig))->getStyleSheetUrl()) . ' />';
        ?>
    <?php endif; ?>
</head>

<body>
    <?php $this->beginBody() ?>

    <!-- <div class="wrap"> -->
    <div class="container">
        <div class="header">
            <img class="header-logo" src="https://doc.samba.ai/wp-content/uploads/2019/06/extended-e1559896302899.png" alt="Samba.ai logo">

            <div class="header-btn-group">
                <?php
                    echo Nav::widget([
                        'items'   => [
                            [
                                'label'   => 'Dokumentacja',
                                'url'     => 'https://doc.samba.ai/wp-content/uploads/Idosell_connector/Samba_ai_connector_Idosell_instrukcja_20250723.pdf',
								'linkOptions' => ['class' => 'header-btn docs-btn']
                            ],
                            [
                                'label'   => 'Zarejestruj się',
								'url' => 'https://app.samba.ai/signup?activePlatform=idosell',
                                'visible' => Yii::$app->user->isGuest || ($userType && $userType === 'idoapp'),
								'linkOptions' => ['class' => 'header-btn register-btn']
                            ],
                            [
                                'label'   => 'Zaloguj się',
								'url' => 'https://app.samba.ai/login',
                                'visible' => Yii::$app->user->isGuest || ($userType && $userType === 'idoapp'),
								'linkOptions' => ['class' => 'header-btn login-btn', 'target' => '_blank']
                            ],
                            [
                                'label'   => 'Wyloguj',
                                'url'     => ['authorization/logout'],
                                'visible' => !Yii::$app->user->isGuest && (!$userType || $userType !== 'idoapp'),
								'linkOptions' => ['class' => 'header-btn button-logout']
                            ],
                        ],
                        'options' => ['class' => 'header__buttons'], // set this to nav-tab to get tab-styled navigation
                    ]);
                ?>
            </div>
        </div>

        <div class="banners-col-half-group">
            <div class="banners-col-half">
				<a href="https://app.samba.ai/signup?activePlatform=idosell">
					<img src="https://d15k2d11r6t6rl.cloudfront.net/pub/h7cu/54w1lxza/kym/4ol/r5s/Benner_1_darmo_do_1000_Sambaai.png" alt="Baner Darmowe Samba.ai">
				</a>
				<a href="https://www.samba.ai/pl/dlaczego-samba">
					<img src="https://d15k2d11r6t6rl.cloudfront.net/pub/h7cu/54w1lxza/tyf/62y/m26/Benner_2_ai_do_pracy_Sambaai.png" alt="Baner AI Samba.ai">
				</a>
            </div>
        </div>

        <?php echo \app\widgets\Alert::widget() ?>

        <?php echo $content ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var popup = document.getElementById('copyPopup');

            document.querySelectorAll('.copy-feed-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const url = btn.getAttribute('data-feed-url');

                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(url).then(function() {
                            showCopied(btn);
                        });
                    } else {
                        // fallback dla starszych przeglądarek
                        const textarea = document.createElement('textarea');
                        textarea.value = url;
                        document.body.appendChild(textarea);
                        textarea.select();

                        try {
                            document.execCommand('copy');
                            showCopied(btn);
                        } catch (err) {
                            //
                        }

                        document.body.removeChild(textarea);
                    }
                });
            });

            function showCopied(btn) {
                const oldText = btn.textContent;
                btn.textContent = 'Skopiowano!';
                btn.classList.add('copied');
                btn.disabled = true;
                popup.classList.add('show');

                setTimeout(function() {
                    btn.textContent = oldText;
                    btn.classList.remove('copied');
                    btn.disabled = false;
                    popup.classList.remove('show');
                }, 1800);
            }
        });
    </script>

	<style>
		:root, body {
			font-size: 16px;
		}

		.header__buttons {
			display: flex;
			gap: 38px;
		}

		.header__buttons::after, .header__buttons::before {
			content: none;
		}

		.label-with-hint {
			display: flex;
			gap: 8px;
		}

		.row::after, .row::before {
			content: none;
		}

		.row {
			margin: auto;
		}

		@media (min-width: 1200px) {
			.container {
				width: 1200px;
			}
		}

		.header-btn.docs-btn:focus,
		.header-btn.docs-btn:hover {
			text-decoration: none;
		}

		/* Date input styling for visual consistency */
		input[type="date"] {
			width: 100%;
			box-sizing: border-box;
			padding: 12px;
			border: 1.5px solid var(--border);
			border-radius: var(--radius);
			font-size: 1.07rem;
			margin-bottom: 16px;
			background: var(--input-bg);
			color: var(--accent);
			transition: border-color 0.2s;
			min-width: 0;
			max-width: 100%;
			display: block;
			/* Remove default browser icon for consistency */
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			/* Add custom calendar icon */
			background: #fff url('data:image/svg+xml;utf8,<svg fill="%23f37221" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 16px center;
			background-size: 22px 22px;
		}

		input[type="date"]:focus {
			border-color: var(--primary);
			outline: none;
		}

		/* Adjust label for better spacing above date input */
		label[for="orders_date_from"] {
			margin-bottom: 8px;
			font-weight: 500;
			color: var(--accent);
			letter-spacing: 0.2px;
		}

		/* Responsive tweaks for mobile */
		@media (max-width: 600px) {
			input[type="date"] {
				font-size: 0.98rem;
				padding: 10px;
			}
		}

		/*date type*/
		.info-tooltip {
			position: relative;
			display: inline-block;
			cursor: pointer;
		}

		.info-icon {
			color: #f37221;
			font-size: 1.3em;
			vertical-align: middle;
			margin-left: 6px;
			user-select: none;
			line-height: 1;
			transition: color 0.2s;
		}

		.info-tooltip:focus .tooltip-text,
		.info-tooltip:hover .tooltip-text {
			visibility: visible;
			opacity: 1;
			pointer-events: auto;
		}

		.tooltip-text {
			visibility: hidden;
			opacity: 0;
			width: 270px;
			background: #091e4f;
			color: #fff;
			text-align: left;
			border-radius: 8px;
			padding: 13px 16px;
			position: absolute;
			z-index: 20;
			left: 50%;
			top: 135%;
			transform: translateX(-50%);
			font-size: 1rem;
			font-weight: 400;
			box-shadow: 0 2px 16px #091e4f22;
			transition: opacity 0.25s;
			pointer-events: none;
			line-height: 1.5;
		}

		.tooltip-text::after {
			content: "";
			position: absolute;
			top: -10px;
			left: 50%;
			transform: translateX(-50%);
			border-width: 0 10px 10px 10px;
			border-style: solid;
			border-color: transparent transparent #091e4f transparent;
		}

		.info-tooltip:focus .info-icon,
		.info-tooltip:hover .info-icon {
			color: #c95d13;
		}

		:root {
			--primary: #f37221;
			--accent: #091e4f;
			--bg: #dff5ff;
			--border: #E5E7EB;
			--radius: 18px;
			--shadow: 0 2px 8px rgba(59, 130, 246, 0.06);
			--table-header: #F1F5F9;
			--section-bg: #fff;
			--input-bg: #fff;
			--desc: #5a6a8a;
		}

		body {
			background: var(--bg);
			color: var(--accent);
			font-family: 'Inter', Arial, sans-serif;
			margin: 0;
			padding: 0;
		}

		.container {
			max-width: 1200px;
			margin: 40px auto;
			background: var(--section-bg);
			border-radius: var(--radius);
			box-shadow: var(--shadow);
			padding: 32px 24px;
		}

		.header {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 32px;
			padding: 16px 24px;
		}

		.header-logo {
			display: block;
			height: 48px;
			/* lub dowolna wysokość */
		}

		.header img {
			height: 64px;
			flex-shrink: 0;
			margin-right: 0;
		}

		.illustration {
			display: block;
			max-width: 340px;
			width: 100%;
			margin: 24px auto 36px auto;
			border-radius: 12px;
			box-shadow: 0 4px 24px #e5e7eb55;
			background: #fff;
		}

		.row {
			display: flex;
			flex-wrap: wrap;
			gap: 32px;
			margin-bottom: 32px;
		}

		.col-half {
			flex: 1 1 0;
			min-width: 320px;
			max-width: 48%;
			background: var(--section-bg);
			border-radius: var(--radius);
			border: 2.5px solid #e5e7eb;
			box-shadow: 0 2px 16px #e5e7eb44;
			padding: 32px 24px 28px 24px;
			margin-bottom: 24px;
		}

		.section-title {
			font-size: 2.1rem;
			color: var(--primary);
			font-weight: 800;
			letter-spacing: 0.5px;
			margin-bottom: 16px;
			background: none;
			padding: 0;
			border: none;
			border-radius: 0;
			box-shadow: none;
			text-transform: none;
			display: block;
		}

		.section-desc {
			color: var(--desc);
			margin-bottom: 24px;
			font-size: 1.06rem;
		}

		label {
			display: block;
			margin-bottom: 8px;
			font-weight: 500;
			color: var(--accent);
			letter-spacing: 0.2px;
		}

		.marketing-label {
			color: var(--accent) !important;
			font-weight: 500 !important;
		}

		input[type="text"],
		input[type="password"],
		select.form-control {
			width: 100%;
			box-sizing: border-box;
			padding: 12px;
			border: 1.5px solid var(--border);
			border-radius: var(--radius);
			font-size: 1.07rem;
			margin-bottom: 16px;
			background: var(--input-bg);
			color: var(--accent);
			transition: border-color 0.2s;
			min-width: 0;
			max-width: 100%;
			display: block;
			height: auto;
		}

		input[type="text"]:focus,
		input[type="password"]:focus,
		select.form-control:focus {
			border-color: var(--primary);
			outline: none;
			box-shadow: none;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.btn {
			background: var(--primary);
			color: #fff;
			padding: 14px 32px;
			border: none;
			border-radius: var(--radius);
			font-size: 1.07rem;
			font-weight: 700;
			cursor: pointer;
			box-shadow: 0 1px 4px #f3722140;
			transition: background 0.2s, transform 0.1s;
			margin-top: 8px;
			margin-bottom: 8px;
			display: inline-block;
			letter-spacing: 0.5px;
			text-transform: uppercase;
		}

		.btn:hover,
		.btn:focus {
			background: #c95d13;
			color: #fff;
			transform: translateY(-2px) scale(1.02);
		}

		@media (max-width: 992px) {
			.header-btn-group {
				/* flex-direction: column;
				gap: 18px;
				align-items: stretch; */
			}

			.header-btn {
				width: 100%;
				min-width: 0;
				text-align: center;
			}

			.header__buttons {
				flex-direction: column;
				gap: 18px;
			}
		}

		select {
			appearance: none;
			background: #fff url('data:image/svg+xml;utf8,<svg fill="%23091e4f" height="32" viewBox="0 0 24 24" width="32" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 16px center;
			background-size: 26px 26px;
			box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.10);
		}

		.feeds-table {
			width: 100%;
			table-layout: fixed;
			border-collapse: separate;
			border-spacing: 0;
			background: #fff;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 1px 6px #e5e7eb70;
			margin-bottom: 24px;
		}

		.feeds-table th,
		.feeds-table td {
			padding: 10px 6px;
			border-bottom: 1px solid #e5e7eb;
			font-size: 1.02rem;
			text-align: center;
			vertical-align: middle;
			word-break: break-word;
		}

		.feeds-table th {
			background: #f1f5f9;
			color: #091e4f;
			font-weight: 800;
			font-size: 1.08rem;
			letter-spacing: 0.7px;
			line-height: 1.2;
		}

		.feeds-table tr:last-child td {
			border-bottom: none;
		}

		.feeds-table a {
			color: var(--primary);
			text-decoration: underline;
			font-weight: 700;
		}

		.copy-feed-btn {
			background: #f37221;
			color: #fff;
			border: none;
			border-radius: 8px;
			font-size: 0.97rem;
			font-weight: 700;
			padding: 6px 16px;
			cursor: pointer;
			min-width: 90px;
			max-width: 100%;
			transition: background 0.2s;
			margin: 4px 0;
			box-shadow: 0 1px 4px #f3722140;
			white-space: normal;
			line-height: 1.3;
		}

		.copy-feed-btn.copied {
			background: #c95d13;
		}

		.copy-feed-btn:hover,
		.copy-feed-btn:focus {
			background: #c95d13;
		}

		.copy-popup {
			display: none;
			position: fixed;
			z-index: 10000;
			left: 50%;
			top: 50%;
			transform: translate(-50%, -50%);
			background: #fff;
			color: #091e4f;
			border-radius: 14px;
			box-shadow: 0 6px 32px #00000030;
			padding: 32px 44px;
			font-size: 1.22rem;
			font-weight: 700;
			border: 2px solid #f37221;
			text-align: center;
			min-width: 220px;
			max-width: 90vw;
			animation: fadeIn 0.2s;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translate(-50%, -60%);
			}

			to {
				opacity: 1;
				transform: translate(-50%, -50%);
			}
		}

		.copy-popup.show {
			display: block;
		}

		@media (max-width: 992px) {
			.row {
				flex-direction: column;
				gap: 0;
			}

			.col-half {
				max-width: 100%;
				margin-bottom: 32px;
			}

			/* .header {
				flex-direction: column;
				align-items: flex-start;
			} */

			.header {
    			/* justify-content: center;
				flex-wrap: wrap; */
				flex-direction: column;
			}

			.header img {
				margin-bottom: 16px;
			}

			/* .login-btn {
				margin-top: 12px;
			} */
		}

		@media (max-width: 600px) {
			.container {
				padding: 12px 2px;
			}

			h1 {
				font-size: 1.5rem;
			}

			.section-title {
				font-size: 1.2rem;
			}

			.feeds-table th,
			.feeds-table td {
				padding: 7px;
				font-size: 0.98rem;
			}

			.copy-popup {
				padding: 18px 10px;
				font-size: 1.01rem;
			}

			.illustration {
				max-width: 98vw;
			}
		}

		.checkbox-list {
			margin: 0;
			padding: 0;
		}

		.checkbox-row {
			display: flex;
			align-items: center;
			padding: 14px 0 14px 0;
			border-bottom: 1.5px solid #e5e7eb;
			transition: background 0.15s;
		}

		.checkbox-row:last-child {
			border-bottom: none;
		}

		.checkbox-row:hover {
			background: #f1f5f9;
		}

		.checkbox-list label {
			font-size: 1.09rem;
			font-weight: 500;
			color: #091e4f;
			cursor: pointer;
			width: 100%;
			display: flex;
			align-items: center;
			margin: 0;
		}

		.checkbox-list input[type="checkbox"] {
			width: 22px;
			height: 22px;
			accent-color: #091e4f;
			margin: 0;
			margin-right: 16px;
			vertical-align: middle;
			border-radius: 6px;
			border: 2px solid #e5e7eb;
			transition: accent-color 0.2s;
		}

		.header-btn-group {
			/* display: flex;
			align-items: center;
			gap: 38px;
			flex-wrap: wrap; */
		}

		.header__buttons.nav .header-btn {
			font-family: 'Inter', Arial, sans-serif;
			font-size: 1.35rem;
			font-weight: 700;
			padding: 16px 36px;
			border-radius: 10px;
			min-width: 160px;
			letter-spacing: 0.5px;
			transition: background 0.18s, color 0.18s, border-color 0.18s;
			cursor: pointer;
			outline: none;
			border: none;
			display: inline-block;
			text-align: center;
		}

		.header__buttons.nav .docs-btn {
			background: #fff;
			color: #173a7a;
			border: 2.5px solid #173a7a;
		}

		.header__buttons.nav .docs-btn:hover,
		.header__buttons.nav .docs-btn:focus {
			background: #f3f6fa;
			color: #173a7a;
			border-color: #0e2447;
		}

		.header__buttons.nav .register-btn {
			background: #173a7a;
			color: #fff;
			border: none;
		}

		.header__buttons.nav .register-btn:hover,
		.header__buttons.nav .register-btn:focus {
			background: #0e2447;
			color: #fff;
		}

		.header__buttons.nav .login-btn {
			background: var(--primary);
			color: #fff;
			border: none;
		}

		.header__buttons.nav .login-btn:hover,
		.header__buttons.nav .login-btn:focus {
			background: #c95d13;
			color: #fff;
		}

		@media (max-width: 992px) {
			.header-btn-group {
				/* flex-direction: column;
				gap: 18px;
				align-items: stretch; */
			}
		}

		/* grafika baner naglowek */
		.banners-col-half-group {
			width: 100%;
			display: flex;
			justify-content: center;
			margin: 32px 0 32px 0;
		}

		.banners-col-half {
			display: flex;
			gap: 40px;
			width: 100%;
			max-width: 1200px;
			/* Match your main container */
			justify-content: center;
			align-items: flex-start;
		}

		/* .banners-col-half a {
			flex: 1 1 0;
			min-width: 0;
			width: 0;
		} */

		.banners-col-half img {
			/* flex: 1 1 0;
			min-width: 0;
			width: 0; */
			/* let flexbox control width */
			max-width: 100%;
			height: auto;
			border-radius: 14px;
			background: #fff;
			box-shadow: 0 4px 24px #e5e7eb55;
			display: block;
			object-fit: cover;
		}

		@media (max-width: 1100px) {
			/* .banners-col-half {
				gap: 40px;
				max-width: 98vw;
			} */

			/* .banners-col-half img {
				width: 45vw;
				max-width: 98vw;
			} */
		}

		@media (max-width: 992px) {
			.banners-col-half {
				flex-direction: column;
				align-items: center;
				gap: 18px;
				max-width: 98vw;
			}

			.banners-col-half img {
				/* width: 98vw; */
				max-width: 98vw;
				width: 100%;
			}
		}

		.video-thumb-link {
			display: block;
			width: 100%;
			max-width: 420px;
			margin: 0 auto;
			border-radius: 12px;
			overflow: hidden;
			box-shadow: 0 4px 24px #e5e7eb55;
			transition: box-shadow 0.2s;
		}

		.video-thumb-link:hover,
		.video-thumb-link:focus {
			box-shadow: 0 8px 32px #173a7a44;
		}

		.video-thumb {
			display: block;
			width: 100%;
			height: auto;
			border-radius: 12px;
		}

		.support-btn {
			text-decoration: none !important;
			font-size: 1.22rem !important;
			font-weight: 700;
		}

		a {
			text-decoration: none;
		}

		.panel-primary > .panel-heading {
			background-color: #0e2447;
			border-radius: var(--radius) var(--radius) 0 0;
		}

		.panel-primary {
			margin: 0 auto;
			max-width: 400px;
			background: var(--section-bg);
			border-radius: var(--radius);
			border: 2.5px solid #e5e7eb;
		}
	</style>

    <?php $this->endBody() ?>
</body>

</html>
<?php $this->endPage() ?>
