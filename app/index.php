<?

	require_once(__DIR__.'/../app/apiKeys.php');
	
	saveShellParametersInGET();

	loadDbs(array('gpt3ideas','gpt3votes','emails','gpt3solutionfinder_ratelimiter'));

	require_once(__DIR__.'/../lib/sendgrid-php/vendor/autoload.php');
	$sendgrid = new \SendGrid($config['sendGridApi']['key']);

	$emailSponsorHTML="<strong>Sponsor: <a href=\"https://remoteok.io/hire-remotely?ref=ideasai\">Hiring remotely? Post a job to 1,000,000+ remote workers</a></strong><br/><br/>\n\n";

	require_once(__DIR__.'/../app/nsfw.php');
	$bannedIdeas=$config['nsfw'];

	parse_str(file_get_contents("php://input"),$_DATA);

	// <set uid to track votes>
		if(!$_COOKIE['ideasai_user_id']) {
			$newUser=true;
			$_COOKIE['ideasai_user_id']=generateRandomString(16);
			setcookie('ideasai_user_id',$_COOKIE['ideasai_user_id'],strtotime("+365 days"), '/; samesite=lax','.ideasai.net',true,true);
		}

		// <fix votes without user_ids based on new user_id and ip and user_agent>
			$query=$gpt3votesDb->prepare("UPDATE gpt3votes SET user_id=:user_id WHERE ip=:ip AND user_agent=:user_agent");
			$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
			$query->bindValue(':user_agent',$_SERVER['HTTP_USER_AGENT']);
			$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
			$query->execute();
		// </fix votes without user_ids based on new user_id and ip and user_agent>

	// </set uid to track votes>


	// <front page>
		if(empty($_GET['action']) && php_sapi_name()!='cli') {
			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC,epoch_created DESC");
			$query->execute();
			$ideas=$query->fetchAll(PDO::FETCH_ASSOC);

			$query=$gpt3ideasDb->prepare("SELECT epoch_created FROM gpt3ideas WHERE claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY epoch_created ASC LIMIT 1");
			$query->execute();
			$oldestIdea=$query->fetchAll(PDO::FETCH_ASSOC)[0];

			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY epoch_created DESC LIMIT 10");
			$query->execute();
			$newIdeas=$query->fetchAll(PDO::FETCH_ASSOC);

			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY epoch_created DESC LIMIT 100");
			$query->execute();
			$latestIdeas=$query->fetchAll(PDO::FETCH_ASSOC);

			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE votes>=2 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC LIMIT 30");
			$query->execute();
			$topIdeas=$query->fetchAll(PDO::FETCH_ASSOC);

			// <sort top ideas by votes and time>
				$newTopIdeas=array();
				foreach($topIdeas as $idea) {
					$ageInHours = (time()-$idea['epoch_created'])/60/60;
					$idea['rank'] = pow(($idea['votes'] - 1) / ($ageInHours + 2),1.5);
					array_push($newTopIdeas,$idea);
				}
				sortBySubkeyFast($newTopIdeas,'rank',false);
				$topIdeas=$newTopIdeas;
			// </sort top ideas by votes and time>


			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE epoch_created>".strtotime("-24 hours")." AND votes>=1 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC LIMIT 10");
			$query->execute();
			$todaysTopIdeas=$query->fetchAll(PDO::FETCH_ASSOC);


			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE epoch_created>".strtotime("-48 hours")." AND epoch_created<".strtotime("-24 hours")." AND votes>=1 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC LIMIT 10");
			$query->execute();
			$yesterdaysTopIdeas=$query->fetchAll(PDO::FETCH_ASSOC);


			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE epoch_created>".strtotime("-7 days")." AND votes>=1 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC LIMIT 10");
			$query->execute();
			$thisWeeksTopIdeas=$query->fetchAll(PDO::FETCH_ASSOC);


			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE epoch_created>".strtotime("-31 days")." AND votes>=1 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC LIMIT 10");
			$query->execute();
			$thisMonthsTopIdeas=$query->fetchAll(PDO::FETCH_ASSOC);



			$query=$emailsDb->prepare("SELECT COUNT(*) FROM emails");
			$query->execute();
			$emailCount=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];

			// <center idea>
				$randomIdea=getRandomIdea();
			// </center idea>



			?>
			<!doctype html>
			<html>
			<link rel="icon" href="/assets/bulb.png" type="image/x-icon"/>
			<meta name="viewport" content="width=device-width, initial-scale=1.0,user-scalable=no,maximum-scale=1.0">
			<title>
				IdeasAI: GPT-3-powered business idea generator
			</title>
			<meta name="twitter:card" content="summary_large_image">
			<meta name="twitter:site" content="@levelsio">
			<meta name="twitter:creator" content="@levelsio">
			<meta name="twitter:title" content="IdeasAI: GPT-3-powered business idea generator">
			<meta name="twitter:description" content="IdeasAI is an A.I. that generates business idea using GPT-3 by OpenAI" />
			<meta name="twitter:image:src" content="https://ideasai.net/assets/social.png?<?=filemtime(__DIR__.'/../assets/social.png')?>">
			<meta property="og:type" content="website"/>
			<meta property="og:title" content="IdeasAI: GPT-3-powered business idea generator"/>
			<meta property="og:image" content="https://ideasai.net/assets/social.png?<?=filemtime(__DIR__.'/../assets/social.png')?>">
			<meta property="og:description" content="IdeasAI is an A.I. that generates business idea using GPT-3 by OpenAI" />
			<meta property="og:url" content="https://ideasai.net<?=$_SERVER['REQUEST_URI']?>">
			<meta name="twitter:url" content="https://ideasai.net<?=$_SERVER['REQUEST_URI']?>">
			<script src="/assets/jquery.min.js??<?=filemtime(__DIR__.'/../assets/jquery.min.js')?>"></script>

			<script>
				var soloIdea=false;
				windowWidth=$(window).width();

				/* <dragging vars> */
					var draggingNow=false;
					var draggingX=0;
					var draggingY=0;
					var dragCardInitialMouseX=0;
					var dragCardInitialMouseY=0;
					var dragCardInitialX=0;
					var dragCardInitialY=0;
					var dragCardWidth=0;
					var dragCardHeight=0;
					var dragRelativeX=0;
					var dragRelativeY=0;
					var draggingCard;
				/* </dragging vars> */

				$(function() {
					
					document.body.style.cursor='default';

					$(window).resize(function() {
						windowWidth=$(window).width();
					});
					
					$('input.email').bind('keyup',function(e) {
						if(e.which==13) {
							/* enter press */
							$('.action-subscribe').click();
						}
					});

					/* <dragging logic> */
						$('body').on('mousedown touchstart','.center-idea-container table',function(e) {
							if(typeof e.originalEvent.touches !=='undefined') {
								/* touch device */
								draggingX=e.originalEvent.touches[0].clientX;
								draggingY=e.originalEvent.touches[0].clientY;
							}
							else {
								/* mouse device */
								draggingX=e.clientX;
								draggingY=e.clientY;
							}
							draggingCard=this;
							draggingNow=true;
							dragCardWidth=$('.center-idea-container table').width();
							dragCardHeight=$('.center-idea-container table').height();
							dragCardInitialX=$('.center-idea-container table').offset().left;
							dragCardInitialY=$('.center-idea-container table').offset().top;
							dragCardInitialMouseX=draggingX-dragCardInitialX;
							dragCardInitialMouseY=draggingY-dragCardInitialY;
						});

						$('body').on('mousemove touchmove',function(e) {
							if(!draggingNow) return;
							if(typeof e.originalEvent.touches !=='undefined') {
								/* touch device */
								draggingX=e.originalEvent.touches[0].clientX;
								draggingY=e.originalEvent.touches[0].clientY;
							}
							else {
								/* mouse device */
								draggingX=e.clientX;
								draggingY=e.clientY;
							}
							dragRelativeX=(draggingX-dragCardInitialX-dragCardInitialMouseX);
							dragRelativeY=(draggingY-dragCardInitialY-dragCardInitialMouseY);

							if(dragRelativeX<-10) {
								$('.center-idea-container .action-upvote').removeClass('active');
								$('.center-idea-container .action-downvote').addClass('active');
							}
							else if(dragRelativeX>10) {
								$('.center-idea-container .action-upvote').addClass('active');
								$('.center-idea-container .action-downvote').removeClass('active');
							}
							else {
								$('.center-idea-container .action-upvote').removeClass('active');
								$('.center-idea-container .action-downvote').removeClass('active');	
							}

							/* <rotate card when dragging> */
								rotateDeg=0;
								if(dragRelativeX>0) {
									rotateDeg=normalize(dragRelativeX,0,dragRelativeX+windowWidth/2)*25;
								} else {
									rotateDeg=-normalize(dragRelativeX,0,dragRelativeX-windowWidth/2)*25;
								}
							/* </rotate card when dragging> */

							$(draggingCard).css('transform','translate3d('+dragRelativeX+'px,'+dragRelativeY+'px,0px) rotate('+rotateDeg+'deg)');
						});
						$('body').on('mouseup touchend',function(e) {
							if(!draggingNow) return;
							draggingNow=false;

							$('.center-idea-container table').addClass('transition');

							/* <check if dragged to left/right> */
								$('.center-idea-container .action-upvote').removeClass('active');
								$('.center-idea-container .action-downvote').removeClass('active');
								console.log('dragRelativeX',dragRelativeX);
								if(dragRelativeX<-100) {
									console.log('drag left: '+dragRelativeX);
									$('.center-idea-container .action-downvote').click();;
									$('.center-idea-container .action-downvote').addClass('active');
									$('.center-idea-container table').addClass('transition');
									$('.center-idea-container table').css('transform','translate3d('+(-windowWidth*2)+'px,0px,0px) rotate(45deg)');
									setTimeout(function() {
										$('.center-idea-container .action-downvote').removeClass('active');
										$('.center-idea-container table').removeClass('transition');
										$('.center-idea-container table').css('transform','none');
									},250);
									return;
								}
								else if(dragRelativeX>100) {
									console.log('drag right: '+dragRelativeX);
									$('.center-idea-container .action-upvote').click();
									$('.center-idea-container table').addClass('transition');
									$('.center-idea-container table').css('transform','translate3d('+(windowWidth*2)+'px,0px,0px) rotate(45deg)');
									
									$('.center-idea-container .action-upvote').addClass('active');
									setTimeout(function() {
										$('.center-idea-container .action-upvote').removeClass('active');
										$('.center-idea-container table').removeClass('transition');
										$('.center-idea-container table').css('transform','none');

									},250);
									return;
								}
								else {
									/* return to center */
									$('.center-idea-container table').addClass('transition');
									$('.center-idea-container table').css('transform','translate3d(0px,0px,0px)');
									setTimeout(function() {
										$('.center-idea-container table').removeClass('transition');
									},125);
								}
							/* </check if dragged to left/right> */



						});
					/* </dragging logic> */




					$('.action-subscribe').bind('click',function() {
						activeAjax=$.ajax({
							async:true,
							url: '/',
							type: 'GET',
							dataType:'json',
							data: {
								action:'subscribe',
								email:$('input.email').val(),
							}}
						).done(function(reply) {
							if(reply.success) {
								$('input.email').val('');
								$('.banner-subscribe').hide();
							}
							alert(reply.message);
						});
					});

					$('.action-upvote').bind('click',function() {

						$('.how-to-use-guide').fadeOut();

						// if($(this).data('solo')==true) {
						// 	draggingCard=$('.center-idea-container table')[0];
						// 	$('.center-idea-container table').addClass('transition');
						// 	$('.center-idea-container table').css('transform','translate3d('+(windowWidth*2)+'px,0px,0px) rotate(45deg)');
						// }

						votes=parseInt($(this).parent().find('.votes').data('votes'))+1;
						$(this).parent().find('.votes').text(votes);
						soloIdea=false;
						console.log($(this).data('id'));
						if($(this).data('solo')) {
							soloIdea=true;
							document.body.style.cursor='wait';
							$('tr#id_'+$(this).data('id')).fadeTo(200,0.00001);
						}
						activeAjax=$.ajax({
							async:true,
							url: '/',
							type: 'GET',
							dataType:'json',
							data: {
								key:'<?=$_GET['key']?>',
								action:'upvote',
								new_idea:$(this).data('idea-type'),
								id:$(this).data('id')
							}}
						).done(function(reply) {
							document.body.style.cursor='default';
							$('tr#id_'+reply.callback_id).focus(); /* to unblur vote button */
							if(soloIdea) {
								console.log('tr#id_'+reply.callback_id);
								$('tr#id_'+reply.callback_id).fadeTo(0.1,1);
								$('tr#id_'+reply.callback_id).find('.idea').text(reply.new_idea.idea);
								$('tr#id_'+reply.callback_id).find('.time_ago').text('Generated by GPT-3, '+reply.new_idea.time_ago+' ago');
								$('tr#id_'+reply.callback_id).find('.action-upvote').data('id',reply.new_idea.id);
								$('tr#id_'+reply.callback_id).find('.action-downvote').data('id',reply.new_idea.id);
								$('tr#id_'+reply.callback_id).find('.votes').data('votes',reply.new_idea.votes);
								$('tr#id_'+reply.callback_id).find('.votes').text(reply.new_idea.votes);
								$('tr#id_'+reply.callback_id).attr('id','id_'+reply.new_idea.id);
							}
						});
					});

					$('.action-downvote').bind('click',function() {
						
						$('.how-to-use-guide').fadeOut();

						votes=parseInt($(this).parent().find('.votes').data('votes'))-1;
						$(this).parent().find('.votes').text(votes);
						soloIdea=false;
						if($(this).data('solo')) {
							soloIdea=true;
							document.body.style.cursor='wait';
							$('tr#id_'+$(this).data('id')).fadeTo(200,0.00001);
						}
						activeAjax=$.ajax({
							async:true,
							url: '/',
							type: 'GET',
							dataType:'json',
							data: {
								key:'<?=$_GET['key']?>',
								action:'downvote',
								new_idea:$(this).data('idea-type'),
								id:$(this).data('id')
							}}
						).done(function(reply) {
							$('tr#id_'+reply.callback_id).focus(); /* to unblur vote button */
							document.body.style.cursor='default';
							if(soloIdea) {
								$('tr#id_'+reply.callback_id).fadeTo(0.1,1);

								$('tr#id_'+reply.callback_id).find('.idea').text(reply.new_idea.idea);
								$('tr#id_'+reply.callback_id).find('.time_ago').text('Generated by GPT-3, '+reply.new_idea.time_ago+' ago');
								$('tr#id_'+reply.callback_id).find('.action-upvote').data('id',reply.new_idea.id);
								$('tr#id_'+reply.callback_id).find('.action-downvote').data('id',reply.new_idea.id);
								$('tr#id_'+reply.callback_id).find('.votes').data('votes',reply.new_idea.votes);
								$('tr#id_'+reply.callback_id).find('.votes').text(reply.new_idea.votes);
								$('tr#id_'+reply.callback_id).attr('id','id_'+reply.new_idea.id);
							}
						});
					});


					/* <keys> */
						$(document).keydown(function(e) {
							if($('input').is(':focus')) {
								return;
							}
							if($('textarea').is(':focus')) {
								return;
							}
							/* <right arrow> */
								if(e.which==39) {
									$('.action-upvote').eq(0).click();
								}
							/* </right arrow> */
							/* <left arrow> */
								if(e.which==37) {
									$('.action-downvote').eq(0).click();
									setTimeout(function() {
										$(window).scrollTop(0);
									},50);
									setTimeout(function() {
										$(window).scrollTop(0);
									},10);
									setTimeout(function() {
										$(window).scrollTop(0);
									},100);
								}
							/* </left arrow> */
						});
					/* </keys> */




				});
				function normalize(val, min, max){
				  if(min < 0){
					max += 0 - min;
					val += 0 - min;
					min = 0;
				  }
				  val = val - min;
				  max = max - min;
				  return Math.max(0, Math.min(1, val / max));
				}
			</script>
			<br/>
			<h1>
				💡 IdeasAI
			</h1>
			<a href="https://beta.openai.com/" target="_blank">
				<img style="margin-top:-27px;margin-bottom:39px;margin-left:41.5px;" src="https://cdn.openai.com/API/logo-assets/powered-by-openai.svg" width="110" alt="Powered by OpenAI" />
			</a>

			<p style="margin-top:-28px;">
				<i>
					<strong>
						GPT-3-powered business idea generator
					</strong>
				</i><br/><br/>
				<strong>Current speed:</strong> <?


						$query=$gpt3ideasDb->prepare("SELECT COUNT(*) FROM gpt3ideas WHERE epoch_created>".strtotime("-31 days")." AND human_seeded IS NOT 1");
						$query->execute();
						$ideasInLastH=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)']/30.5;

						if($ideasInLastH<10) {
							$query=$gpt3ideasDb->prepare("SELECT COUNT(*) FROM gpt3ideas WHERE epoch_created>".strtotime("-24 hours")." AND human_seeded IS NOT 1");
							$query->execute();
							$ideasInLastH=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)']/24;
						}
					echo number_format($ideasInLastH);
				?> ideas generated today.<br/>
				<?=number_format(count($ideas))?> ideas generated so far.<br/>
			</p>
			<p style="margin-bottom:28px;">
				by <a href="https://twitter.com/levelsio">@levelsio</a>
			</p>
			
			<style>
				:root {
					--input-border-color:#dddddd;
					--box-shadow-central:0 0 0 1px var(--input-border-color), 0 2px 4px 0 rgb(0 0 0 / 7%), 0 1px 1.5px 0 rgb(0 0 0 / 5%);
				}

				body,
				input,
				textarea {
					font-family:-apple-system, system-ui, "Segoe UI", Helvetica, Arial, sans-serif;
				}
				input.problem {
					font-size:15px;
				}
				input,textarea,div.solution {
					background:#fff;
					box-shadow:0 1px 2px 0 rgba(0,0,0,.1);
					border:none;
					margin:14px;
					outline:none;
					appearance:none;
				}
				div.solution {
					font-size: 24px !important;
    				font-weight: bold;
    				color: #000;
    				width:calc(100% - 14px - 14px - 14px);
    				min-height:200px;
    				margin-top:-7px;
				}
				button {
					border:1px solid #ddd;
					cursor:pointer;
				}
				button:hover {
					opacity:0.75;
				}
				button:active {
					opacity:0.5;
				}
				input,textarea,button,div.solution {
					font-size:14px;
					appearance:none;
					padding:14px;
					border-radius:5px;
				}
				body {
					padding:14px;
					background:#f9f9f9;
					text-align:center;
				}
				table {
					box-shadow:var(--box-shadow-central);
					background:#fff;
					border-radius:5px;
					border-collapse:collapse;
					width:100%;
					max-width:700px;
				}
				p {
					width:100%;
					max-width:700px;
					margin:14px auto;
				}
				table tr td {
					font-weight:bold;
					padding:21px;
				}
				h2 {
					max-width:700px;
					width:100%;
					text-align:left;
					display:block;
					margin:14px auto;
				}

				.action-upvote {
					margin-top:5px;
					margin-left:7px;
				}
				.action-upvote svg {
					fill:#ff4742;
					height:30px;
				}
				.action-downvote svg {
					margin-right:7px;
					height:35px;
				}
				.action-downvote:hover svg,
				.action-upvote:hover svg {
					transform: scale(1.2856);
					-webkit-transform: scale(1.2856);
					-ms-transform: scale(1.2856);
				}
				.action-upvote,
				.action-downvote {
					cursor:pointer;
					display:inline-block;
					vertical-align:middle;
				}
				tr .votes {
					display:inline-block;
					vertical-align:middle;
				}

				@media (min-width:600px) {
					.action-upvote:active,
					.action-downvote:active {
						opacity:0.25;
					}
				}

				a {
					color:#000;
				}
				a:hover {
					opacity:0.75;
				}
				a:active {
					opacity:0.5;
				}
				table tr:hover td {
					/*background:#f9f9f9;*/
				}
				.time_ago {
					opacity:0.5;
					font-weight:600;
					font-size:12px;
				}
				.center-idea-container {
					padding:20vh;
					padding-bottom:calc(22vh);
				}
				.center-idea-container table.transition {
					transition:transform 1s;
					transition-timing-function: cubic-bezier(0.1, 0.7, 1.0, 0.1);
				}
				.center-idea-container table {
					-webkit-touch-callout: none;
					-webkit-user-select: none;
					-khtml-user-select: none;
					-moz-user-select: none;
					-ms-user-select: none;
					user-select: none;
					cursor: grab;
				}
				.center-idea-container table:hover {
					/*opacity: 0.75;*/
				}
				.center-idea-container table:active,
				.center-idea-container table.active {
					cursor: grabbing;
					/*opacity: 0.5;*/
				}
				/*@media (max-height:1000px) {*/
					.center-idea-container {
						padding-top:10vh;
						padding-bottom:calc(14vh + 100px);
					}
				/*}*/
				/*@media (max-width:600px) {*/
					.center-idea-container {
						padding-top:0;
						padding-left:0;
						padding-right:0;
						padding-bottom:calc(22vh + 100px);
					}
					p.text {
						padding:14px;
						width:calc(100% - 14px - 14px);
					}
				/*}*/
				.center-idea-container table {
					border:none;
					border-radius:12px;
					z-index: 2;
				}
				.button {
					border:1px solid #000;
					background:#000;
					color:#fff;
					font-weight:bold;
					text-align:center;
					padding:6px;
					border-radius:5px;
					display:inline-block;
					cursor:pointer;
					padding-top:13px;
					padding-bottom:13px;
				}
				.button:hover {
					background:none;
					color:#000;
				}
				.button:active {
					opacity:0.5;
				}
				input.email {
					margin-bottom:0;
					margin-top:-1px;
					margin-left:14px;
					appearance:none;
					font-size:16px;
					border:1px solid #ddd;
					text-align:left;
					padding:11px;
					border-radius:5px;
					display:inline-block;
					box-shadow:var(--box-shadow-central);
					border:none;
				}
				.td_votes {
					width:140px;
				}
				.td_idea {
					text-align:left;
				}
				/*@media (max-width:1000px) {*/
					table {
						display:block;
					}
					.td_idea,
					.td_votes {
						display:block;
						text-align:center;
						width:auto;
					}
					.td_idea {
						border:none;
						padding-bottom:14px;
					}
					.td_votes {
						padding-top:0;
						width:calc(100% - 14px - 14px - 14px);
					}
				/*}*/
				.button.action-subscribe {
					padding-top:6px;
					padding-bottom:6px;
				}
			</style>

			<div class="center-idea-container">
				<?generateIdeaTable($randomIdea,true,'random');?>

				<p style="font-size:14px;" class="how-to-use-guide">
					Press <strong>X</strong> or your left arrow key to dislike, press ❤️ or your right arrow key to like the idea
				</p>
			</div>


			<?if(!$_COOKIE['ideasai_subscribed']){?>
				<div class="banner-subscribe" style="position:fixed;left:0;width:100vw;bottom:0;text-align:center;line-height:1.8;background:#fff;padding:14px;box-shadow:0 -1px -2px 0 rgba(0,0,0,.1);margin:0 auto;z-index:100;border-top:1px solid #eee;">
					<strong style="width:calc(100% - 28px);display:block;">
						Join <?=number_format($emailCount)?> people who get the best new ideas by GPT-3 in their email weekly
						<input tabindex="1" type="email" class="email" placeholder="Type your email...">
						<div tabindex="2" class="button action-subscribe">
							Subscribe
						</div>
					</strong>
				</div>	
			<?}?>






			<?/* 2020-10-01 discontinued cause billing too expensive

			<a name="solver"></a>
			<h2>
				🧠&nbsp; Ask for a product idea that solves a problem
			</h2>
			<p style="text-align:left;">
				GPT-3 has been asked <?
				$query=$gpt3solutionfinder_ratelimiterDb->prepare("SELECT COUNT(*) FROM gpt3solutionfinder_ratelimiter");
					$query->execute();
					echo number_format($query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)']);
				?> times for an idea now
			</p>
			<div style="text-align:left;max-width:728px;width:100%;margin:0 auto;">
				<input class="problem" maxlength="125" type="text" placeholder="Describe a problem in detail you'd like to find a product idea for" style="float:left;width:calc(100% - 140px);" />
				<div class="button action-find-solution" style="float:right;margin-top:14px;">
					Find an idea
				</div>
				<div style="clear:both"></div>
				<div class="solution">
				</div>

				<script>
					$(function() {
						$('.action-find-solution').bind('click',function() {
							document.body.style.cursor='wait';
							$('div.solution').text('Loading...');
							activeAjax=$.ajax({
								async:true,
								url: '/',
								type: 'GET',
								dataType:'json',
								data: {
									action:'find_solution',
									problem:$('input.problem').val(),
								}}
							).done(function(reply) {
								document.body.style.cursor='default';
								if(reply.success) {
									$('div.solution').text(reply.solution);
								}
								else {
									alert(reply.message);
								}
							});
						});
					})
				</script>
			</div>


			<br/>
			<br/>

			*/?>



			<p clas="text" style="text-align:center;line-height:1.8;">
				<strong>
					Ideas on this page are 100% generated by <a href="https://openai.com/">OpenAI</a>'s GPT-3. <a href="#readme">Read more</a>
				</strong>
			</p>

			<br/>
			<br/>



			<?if($thisMonthsTopIdeas) {?>
				<h2>
					🗓 This month's top ideas
				</h2>
				<?generateIdeaTable($thisMonthsTopIdeas);?>
				<br/>
			<?}?>

			<?if($thisWeeksTopIdeas) {?>
				<h2>
					🗓 This week's top ideas
				</h2>
				<?generateIdeaTable($thisWeeksTopIdeas);?>
				<br/>
			<?}?>

			<?if($yesterdaysTopIdeas) {?>
				<h2>
					🗓 Yesterday's top ideas
				</h2>
				<?generateIdeaTable($yesterdaysTopIdeas);?>
				<br/>
			<?}?>

			<?if($todaysTopIdeas) {?>
				<h2>
					☀️ Today's top ideas
				</h2>
				<?generateIdeaTable($todaysTopIdeas);?>
				<br/>
			<?}?>


			<h2>
				🤔&nbsp; New ideas just in
			</h2>
			<?generateIdeaTable($newIdeas);?>
			<br/>




			<h2>
				🔥 All-time top ideas
			</h2>
			<?generateIdeaTable($topIdeas);?>
			<br/>





			<h2>
				🤔&nbsp; Latest ideas
			</h2>
			<?generateIdeaTable($latestIdeas);?>
			<br/>


			<a name="readme"></a>

			<p clas="text" style="text-align:left;line-height:1.8;">
				The idea (lol) is to give you inspiration to make something cool, if you lack inspiration right now. Many ideas here might not be perfect but they might give you the spark to start thinking to get to a really good idea further on.
			</p>
			<p clas="text" style="text-align:left;line-height:1.8;">
				Please ❤️ like ideas that you feel are promising and ❌ dislike ideas that are bad, too vague, too obvious, or already exist. Ideas and their rating are fed back into OpenAI, so you (and others) constantly train the model what good and bad ideas are which improves the next ideas it comes up with! At least, that's the idea (aye!).
			</p>
			<p clas="text" style="text-align:left;line-height:1.8;">
				The A.I. never sleeps, so it's continously thinking of new ideas and when it comes up with a new one, it shows up here automatically (scroll down to New to see them come in). If you like an idea you can claim it and you get it exclusively and it gets removed from the site so that nobody else can find out about it.
			</p>
			<div clas="text" style="text-align:center;line-height:1.8;padding:14px;border:1px solid #000;border-radius:5px;display:table;margin:0 auto">
				<strong>
					If you'd like to advertise on this page and the <?=number_format($emailCount)?>-subscriber weekly idea mail, <a href="https://twitter.com/levelsio">tweet me</a>!
				</strong>
			</div>


			<br/>
			<br/>

			<br/>
			<br/>

			<br/>
			<br/>

			<br/>
			<br/>




			<script src="https://js.stripe.com/v3"></script>
			<script>
				var stripe = Stripe('pk_live_51HAH3RLuI4dqzJZCcpn0kvwkOjQIu2frZrGvKVwdrz3Rim1W7LvsgUEmA6q2Kn0hDyMFy6zzK2jFLiM5d4nw1lbS00QW5ZI0Yq');
				$(function() {
					$('.claim-idea').bind('click',function() {
						stripe.redirectToCheckout({
							lineItems: [{price: 'price_1HNJGwLuI4dqzJZCGAekSbFG', quantity: 1}],
							mode: 'payment',
							// Do not rely on the redirect to the successUrl for fulfilling
							// purchases, customers may not always reach the success_url after
							// a successful payment.
							// Instead use one of the strategies described in
							// https://stripe.com/docs/payments/checkout/fulfillment
							successUrl: 'https://ideasai.net',
							cancelUrl: 'https://ideasai.net',
						})
						.then(function (result) {
							if (result.error) {
								// If `redirectToCheckout` fails due to a browser or network
								// error, display the localized error message to your customer.
								var displayError = document.getElementById('error-message');
								displayError.textContent = result.error.message;
							}
						});
					});
				});
			</script>

			<script async defer src="https://scripts.simpleanalyticscdn.com/latest.js"></script>
			<noscript><img src="https://queue.simpleanalyticscdn.com/noscript.gif" alt=""/></noscript>

			<?
			exit;
		}
	// </front page>
	// <subscribe email>
		else if($_GET['action']=='subscribe') {

			$_GET['email']=trim(strtolower($_GET['email']));
		
			if(!filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
				echo json_encode(array('success'=>false,'message'=>"That email isn't valid"));
				exit;
			}

			$query=$emailsDb->prepare("SELECT COUNT(*) FROM emails WHERE email=:email AND confirmed=1");
			$query->bindValue(':email',$_GET['email']);
			$query->execute();
			$alreadySubscribed=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];

			if($alreadySubscribed) {
				echo json_encode(array('success'=>false,'message'=>"That email is already subscribed"));
				exit;
			}

			$query=$emailsDb->prepare("INSERT INTO emails(epoch,email,ip) VALUES(:epoch,:email,:ip)");
			$query->bindValue(':epoch',time());
			$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
			$query->bindValue(':email',$_GET['email']);
			$query->execute();

			setcookie('ideasai_subscribed','true',strtotime("+365000 days"), '/; samesite=lax','.ideasai.net',true,true);

			// <send confirm email>
				$hash=md5($_GET['email'].'_ideasai_12484');
				$sendGridMail = new \SendGrid\Mail\Mail(); 
				$sendGridMail->setFrom('no-reply@ideasai.net','IdeasAI');
				$sendGridMail->setSubject("Confirm your email on IdeasAI");
				$sendGridMail->addTo($_GET['email'],$_GET['email']);
				$sendGridMail->addContent("text/plain","Hi,

You (or someone else) entered your email to subscribe to IdeasAI.

If you didn't, just ignore this message. If you did you can click below to confirm your email:

https://ideasai.net/?action=confirm_email&email=".urlencode($_GET['email'])."&hash=".$hash."

");
				try {
					$response = $sendgrid->send($sendGridMail);
				} catch (Exception $e) {
					json_encode(array(
						'success'=>false,
						'message'=>"Could not send confirm email: ".$e->getMessage()."\n"
					));
					exit;
				}
			// </send confirm email>

			echo json_encode(array(
				'success'=>true,
				'message'=>'Check your email and click the link to confirm your email!'
			));

			exit;
		}
	// </subscribe email>
	// <confirm email>
		else if($_GET['action']=='confirm_email') {
			$hash=md5($_GET['email'].'_ideasai_12484');
			if($hash!=$_GET['hash']) {
				echo "Hash doesn't match, tweet @levelsio please";
				exit;
			}

			$query=$emailsDb->prepare("UPDATE emails SET confirmed=1 WHERE email=:email");
			$query->bindValue(':email',$_GET['email']);
			$query->execute();

			?><script>
				alert("Confirmed your email! You'll get your first ideas within an hour.");
				window.location.href='/';
			</script><?
			exit;

		}
	// </confirm email>
	// <unsubscribe email>
		else if($_GET['action']=='stop_receiving') {
			$hash=md5($_GET['email'].'_ideasai_12484');
			if($hash!=$_GET['hash']) {
				echo "Hash doesn't match, tweet @levelsio please";
				exit;
			}

			$query=$emailsDb->prepare("UPDATE emails SET unsubscribed=1 WHERE email=:email");
			$query->bindValue(':email',$_GET['email']);
			$query->execute();

			?><script>
				alert("Unsubscribed your email!");
				window.location.href='/';
			</script><?
			exit;

		}
	// </unsubscribe email>
	// <send out emails>
		else if($_GET['action']=='send_emails') {
		
			// send weekly email
			$query=$emailsDb->prepare("SELECT * FROM emails WHERE unsubscribed IS NOT 1 AND confirmed=1 AND (epoch_last_emailed IS NULL OR epoch_last_emailed<".strtotime("-7 days").")");
			$query->execute();
			$emailsToSend=$query->fetchAll(PDO::FETCH_ASSOC);

			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE epoch_created>".strtotime("-7 days")." AND votes>=1 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY votes DESC LIMIT 25");
			$query->execute();
			$thisWeeksTopIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
			$weeklyTopIdeasList='';
			$i=1;
			foreach($thisWeeksTopIdeas as $idea) {
				$idea['idea']=fixIdeaText($idea['idea']);
				$weeklyTopIdeasList=$weeklyTopIdeasList . $i.'. '.$idea['idea'].' ('.$idea['votes'].'pts)'."\n".
				'<a href="https://ideasai.net/?e=1&action=upvote&id='.$idea['id'].'">❤️ Like this</a>'.
				"<br/><br/>\n\n";
				$i++;
			}

			$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY epoch_created DESC LIMIT 10");
			$query->execute();
			$newIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
			$newIdeasList='';
			$i=1;
			foreach($newIdeas as $idea) {
				$idea['idea']=fixIdeaText($idea['idea']);
				$newIdeasList=$newIdeasList . $i.'. '.$idea['idea'].' ('.$idea['votes'].'pts)'."\n".
				'<a href="https://ideasai.net/?e=1&action=upvote&id='.$idea['id'].'">❤️  Like this</a>'.
				"<br/><br/>\n\n";;
				$i++;
			}

			$sentEmails=0;
			foreach($emailsToSend as $email) {

				$email['email']=trim($email['email']);

				if(!filter_var($email['email'], FILTER_VALIDATE_EMAIL)) {
					continue;
				}

				$query=$emailsDb->prepare("UPDATE emails SET epoch_last_emailed=".time()." WHERE email=:email");
				$query->bindValue(':email',$email['email']);
				$query->execute();

				$hash=md5($email['email'].'_ideasai_12484');
				$sendGridMail = new \SendGrid\Mail\Mail(); 
				$sendGridMail->setFrom('no-reply@ideasai.net','IdeasAI');
				$sendGridMail->setSubject("Your weekly ideas from 💡 IdeasAI");
				$sendGridMail->addTo($email['email'],$email['email']);
				$message="<strong>Here's your weekly ideas generated by GPT-3 from <a href=\"https://ideasai.net\">💡 IdeasAI</a>:</strong><br/><br/>
".$emailSponsorHTML."
".$weeklyTopIdeasList."
<br/>
<strong>And here's the new ideas just in:</strong><br/>
<br/>
".$newIdeasList."<br/>
<br/>
If you don't want to get these weekly ideas anymore, <a href=\"https://ideasai.net/?action=stop_receiving&email=".urlencode($email['email'])."&hash=".$hash."\">click here</a>";

				// echo $message;
				// echo "\n\n";
				
				$sendGridMail->addContent("text/html",$message);

				try {
					$response = $sendgrid->send($sendGridMail);
				} catch (Exception $e) {
					echo json_encode(array(
						'success'=>false,
						'message'=>"Could not send confirm email: ".$e->getMessage()."\n"
					));
					continue;
				}


				echo $email['email'];
				echo "\n";

				$sentEmails++;
			}

			if($sentEmails) {
				// sendToAdminTelegram("💌 Emailed ".number_format($sentEmails)." idea newsletters");
			}

		}
	// </send out emails>
	// <find solution to problem>
		else if($_GET['action']=='find_solution'){
			exit;


			// echo json_encode(array(
			// 	'success'=>false,
			// 	'message'=>'People were spamming/abusing it a bit, so I will fix it up and get it back up tomorrow'
			// ));
			// exit;

			foreach($bannedIdeas as $bannedIdea) {
				if(stripos($_GET['problem'],$bannedIdea)!==false) {
					echo json_encode(array(
						'success'=>true,
						'solution'=>"Try a different problem (3)"
					));
					exit;
				}
			}

			// <check rate limiter>
				$query=$gpt3solutionfinder_ratelimiterDb->prepare("SELECT COUNT(*) FROM gpt3solutionfinder_ratelimiter WHERE (ip=:ip OR user_id=:user_id) AND epoch>".strtotime("-1 seconds"));
				$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
				$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
				$query->execute();
				$count=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];
				if($count) {
					echo json_encode(array(
						'success'=>true,
						'solution'=>'You\'re going too fast, slow down a bit :)'
					));
					exit;
				}
				$query=$gpt3solutionfinder_ratelimiterDb->prepare("SELECT COUNT(*) FROM gpt3solutionfinder_ratelimiter WHERE (ip=:ip OR user_id=:user_id) AND epoch>".strtotime("-24 hours"));
				$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
				$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
				$query->execute();
				$count=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];
				if($count>250) {
					echo json_encode(array(
						'success'=>true,
						'solution'=>'You\'re going too fast, slow down a bit :)'
					));
					exit;
				}
			// </check rate limiter>


			// <content filter>
				$fields=array(
					'prompt'=>	"<|endoftext|>".$_GET['problem']."\n--\nLabel:",
					'temperature'=>0,
					'max_tokens'=>1,
					'top_p'=>0
				);
				$url='https://api.openai.com/v1/engines/content-filter-alpha-c4/completions';
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Authorization: Bearer '.$config['openAiGPT3']['key'],
					'Content-Type:application/json'
				));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch,CURLOPT_POST, json_encode($fields));
				curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($fields));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $url);
				$result=curl_exec($ch);
				curl_close($ch);
				$result = json_decode($result,true);
				$safety=$result['choices'][0]['text'];
				if($safety==1 || $safety==2) {
					echo json_encode(array(
						'success'=>true,
						'solution'=>"Try a different problem (1)"
					));
					exit;
				}
			// </content filter>


			// <send prompt>
				$foundSolutionThatIsSafe=false;
				while(!$foundSolutionThatIsSafe && $triedTimes<10) {

					$fields=array(
						'prompt'=>	"Problem: ".$_GET['problem']."\n".
									"Product idea that solves it:",
						
						'max_tokens'=>100,
						'temperature'=>0.7,
						
						'top_p'=>1,

						'frequency_penalty'=>0,

						'presence_penalty'=>0,
						
						'best_of'=>1,
						'n'=>1,
						'stream'=>false,
						'logprobs'=>null,
						'stop'=>'\n'
					);
					$url='https://api.openai.com/v1/engines/curie/completions';
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Authorization: Bearer '.$config['openAiGPT3']['key'],
						'Content-Type:application/json'
					));
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch,CURLOPT_POST, json_encode($fields));
					curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($fields));
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_URL, $url);
					$result=curl_exec($ch);
					$result = json_decode($result,true);
					curl_close($ch);
					
					$solution=trim($result['choices'][0]['text']);
					$solution=preg_replace('/\xc2\xa0/', ' ', $solution);
					$lines=explode("\n",$solution);
					$solution=trim($lines[0]);
					$lines=explode("Problem:",$solution);
					$solution=trim($lines[0]);

					$triedTimes++;

					if($solution==$_GET['problem']) {
						continue;
					}
					if(strlen($solution)<30) {
						continue;
					}
					if(empty($solution)) {
						continue;
					}

					// <content filter for solution>
						$fields=array(
							'prompt'=>	"<|endoftext|>".$solution."\n--\nLabel:",
							'temperature'=>0,
							'max_tokens'=>1,
							'top_p'=>0
						);
						$url='https://api.openai.com/v1/engines/content-filter-alpha-c4/completions';
						$ch = curl_init();
						curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		                    'Authorization: Bearer '.$config['openAiGPT3']['key'],
							'Content-Type:application/json'
						));
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
						curl_setopt($ch,CURLOPT_POST, json_encode($fields));
						curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($fields));
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_URL, $url);
						$result=curl_exec($ch);
						curl_close($ch);
						$result = json_decode($result,true);
						$safety=$result['choices'][0]['text'];
						if($safety==0) {
							$foundSolutionThatIsSafe=true;
						}
						else {
							continue;
						}
					// </content filter for solution>
				}
			// </send prompt>



			$query=$gpt3solutionfinder_ratelimiterDb->prepare("INSERT INTO gpt3solutionfinder_ratelimiter(epoch,ip,user_id,problem,solution) VALUES(:epoch,:ip,:user_id,:problem,:solution)");
			$query->bindValue(':epoch',time());
			$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
			$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
			$query->bindValue(':problem',$_GET['problem']);
			$query->bindValue(':solution',$solution);
			$query->execute();


			echo json_encode(array(
				'success'=>true,
				'solution'=>$solution,
				'strlen'=>strlen($solution),
				'tried'=>$triedTimes,
				'foundSolutionThatIsSafe'=>$foundSolutionThatIsSafe
			));

			exit;
		}
	// </find solution to problem>
	// <voting logic>
		else if($_GET['action']=='upvote') {

			if(empty($_COOKIE['ideasai_user_id'])) {
				// fake success, dont' save vote
				echo json_encode(
					array(
						'success'=>true
					)
				);
				exit;
			}

			$query=$gpt3votesDb->prepare("SELECT COUNT(*) FROM gpt3votes WHERE (user_id=:user_id OR ip=:ip) AND id=:id AND upvote=1");
			$query->bindValue(':id',$_GET['id']);
			$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
			$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
			$query->execute();
			$alreadyVotedOnThis=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];

			if(!$alreadyVotedOnThis) {
				$query=$gpt3ideasDb->prepare("UPDATE gpt3ideas SET epoch_last_voted=:epoch_last_voted,votes=votes+1,upvotes=upvotes+1 WHERE id=:id");
				$query->bindValue(':id',$_GET['id']);
				$query->bindValue(':epoch_last_voted',time());
				$query->execute();


				$query=$gpt3votesDb->prepare("INSERT INTO gpt3votes(epoch,user_id,ip,id,upvote) VALUES(:epoch,:user_id,:ip,:id,1)");
				$query->bindValue(':epoch',time());
				$query->bindValue(':id',$_GET['id']);
				$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
				$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
				$query->execute();

				$query=$gpt3ideasDb->prepare("SELECT idea FROM gpt3ideas WHERE id=:id");
				$query->bindValue(':id',$_GET['id']);
				$query->execute();
				$idea=$query->fetchAll(PDO::FETCH_ASSOC)[0]['idea'];

				// sendToAdminTelegram("👍 ".$_SERVER['REMOTE_ADDR'].' '.$idea);
			}

			if($_GET['e']) {
				?><script>
					alert("Liked this idea, thanks!");
					window.location.href='/';
				</script><?
			}

			// <get new idea to dynamically replace idea with>
				if($_GET['new_idea']) {

					$alreadyVotedOnThisNewIdea=true;
					while($alreadyVotedOnThisNewIdea) {
						if($_GET['new_idea']=='random') {
							$idea=getRandomIdea();
						}

						if($_GET['new_idea']=='good') {
							$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND claimed IS NOT 1 AND human_seeded IS NOT 1 AND votes>=4 ORDER BY RANDOM() ASC LIMIT 1");
							$query->bindValue(':id',$_GET['id']);
							$query->execute();
							$idea=$query->fetchAll(PDO::FETCH_ASSOC);
						}

						if($_GET['new_idea']=='new') {
							$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND votes=0 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY epoch_created DESC LIMIT 1");
							$query->bindValue(':id',$_GET['id']);
							$query->execute();
							$idea=$query->fetchAll(PDO::FETCH_ASSOC);
						}

						foreach($bannedIdeas as $bannedIdea) {
							if(stripos($idea[0]['idea'],$bannedIdea)!==false) {
								// banned word
								continue(2);
							}
						}

						if(!empty($idea[0]['idea'])) {
							$query=$gpt3votesDb->prepare("SELECT COUNT(*) FROM gpt3votes WHERE user_id=:user_id AND id=:id");
							$query->bindValue(':id',$idea[0]['id']);
							$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
							$query->execute();
							$alreadyVotedOnThisNewIdea=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];
						}


					}

					echo json_encode(
						array(
							'success'=>true,
							'alreadyVotedOnThis'=>$alreadyVotedOnThis ? 'true' : 'false',
							'callback_id'=>$_GET['id'],
							'new_idea'=>array(
								'idea'=>fixIdeaText($idea[0]['idea']),
								'id'=>$idea[0]['id'],
								'time_ago'=>timeAgoLong($idea[0]['epoch_created']),
								'votes'=>$idea[0]['votes']
							)
						)
					);
				}
				else {
					echo json_encode(
						array(
							'success'=>true,
							'alreadyVotedOnThis'=>$alreadyVotedOnThis ? true : false
						)
					);
				}
			// </get new idea to dynamically replace idea with>

			exit;
		}
		else if($_GET['action']=='downvote') {

			if(empty($_COOKIE['ideasai_user_id'])) {
				// fake success, dont' save vote
				echo json_encode(
					array(
						'success'=>true
					)
				);
				exit;
			}

			$query=$gpt3votesDb->prepare("SELECT COUNT(*) FROM gpt3votes WHERE (user_id=:user_id or ip=:ip) AND id=:id AND downvote=1");
			$query->bindValue(':id',$_GET['id']);
			$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
			$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
			$query->execute();
			$alreadyVotedOnThis=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];
			if(!$alreadyVotedOnThis) {

				$query=$gpt3ideasDb->prepare("UPDATE gpt3ideas SET epoch_last_voted=:epoch_last_voted,votes=votes-1,downvotes=downvotes+1 WHERE id=:id");
				$query->bindValue(':id',$_GET['id']);
				$query->bindValue(':epoch_last_voted',time());
				$query->execute();

				$query=$gpt3votesDb->prepare("INSERT INTO gpt3votes(epoch,user_id,ip,id,upvote) VALUES(:epoch,:user_id,:ip,:id,1)");
				$query->bindValue(':epoch',time());
				$query->bindValue(':id',$_GET['id']);
				$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
				$query->bindValue(':ip',$_SERVER['REMOTE_ADDR']);
				$query->execute();

				$query=$gpt3ideasDb->prepare("SELECT idea FROM gpt3ideas WHERE id=:id");
				$query->bindValue(':id',$_GET['id']);
				$query->execute();
				$idea=$query->fetchAll(PDO::FETCH_ASSOC)[0]['idea'];

				// sendToAdminTelegram("❌ ".$_SERVER['REMOTE_ADDR'].' '.$idea);

			}

			// <get new idea to dynamically replace idea with>
				if($_GET['new_idea']) {
					if($_GET['new_idea']=='random') {
						$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND claimed IS NOT 1 AND human_seeded IS NOT 1 AND votes>=0 ORDER BY RANDOM() ASC LIMIT 1");
						$query->bindValue(':id',$_GET['id']);
						$query->execute();
						$idea=$query->fetchAll(PDO::FETCH_ASSOC)[0];
					}

					if($_GET['new_idea']=='good') {
						$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND claimed IS NOT 1 AND human_seeded IS NOT 1 AND votes>=4 ORDER BY RANDOM() ASC LIMIT 1");
						$query->bindValue(':id',$_GET['id']);
						$query->execute();
						$idea=$query->fetchAll(PDO::FETCH_ASSOC)[0];
					}

					if($_GET['new_idea']=='new') {
						$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND votes=0 AND claimed IS NOT 1 AND human_seeded IS NOT 1 ORDER BY epoch_created DESC LIMIT 1");
						$query->bindValue(':id',$_GET['id']);
						$query->execute();
						$idea=$query->fetchAll(PDO::FETCH_ASSOC)[0];
					}

					echo json_encode(
						array(
							'callback_id'=>$_GET['id'],
							'success'=>true,
							'new_idea'=>array(
								'idea'=>fixIdeaText($idea['idea']),
								'id'=>$idea['id'],
								'time_ago'=>timeAgoLong($idea['epoch_created']),
								'votes'=>$idea['votes']
							)
						)
					);
				}
				else {
					echo json_encode(
						array(
							'success'=>true,
						)
					);
				}
			// </get new idea to dynamically replace idea with>

			exit;
		}
	// </voting logic>
	// <generating ideas>
		else if(php_sapi_name()=='cli' && $_GET['action']=='generate_ideas'){

			// echo "This app generates new startup ideas from scratch: "."\n\n";
			
			// source

			// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE human_seeded=1 OR votes>=2 ORDER BY random()LIMIT 50");
			// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE human_seeded=1 AND launch_hn IS NOT 1 ORDER BY random() LIMIT 105");
			// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE ycombinator=1 ORDER BY random() LIMIT 50");
			// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE human_seeded=1 OR (votes>10 and downvotes<10) LIMIT 25");
			// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE (human_seeded=1 OR votes>1) ORDER BY RANDOM() DESC LIMIT 25");
			// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas ORDER BY RANDOM() DESC LIMIT 25");

			// // <get bad idea>
			// 	$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE votes<-1 ORDER BY random() LIMIT 10");
			// 	$query->execute();
			// 	$badIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
			// // </get bad ideas>

			// <get good ideas>
				// $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE launch_hn IS NOT 1 AND (human_seeded=1 OR votes>=20) ORDER BY RANDOM() DESC LIMIT 20");
				$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE launch_hn IS NOT 1 AND (claimed IS NOT 1 AND human_seeded=1) ORDER BY RANDOM() DESC LIMIT 20");
				// // $query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE votes>=10 ORDER BY votes DESC LIMIT 10");
				$query->execute();
				$goodIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
			// </get good ideas>

			// $ideaList='This is a startup idea generator. It generates startup ideas that are high risk, high reward, disruptive and have a large market in the future.'."\n\n";
			// foreach($badIdeas as $idea){
			// 	$ideaList=$ideaList."Bad idea: ".$idea['idea']."\n";
			// }
			foreach($goodIdeas as $idea){
				$ideaList=$ideaList."Idea: ".$idea['idea']."\n";
			}
			$ideaList=$ideaList."Idea: ";

			echo "\n\n";
			echo $ideaList;
			echo "\n\n";

			// echo $ideaList;
			// echo "\n\n";

			$fields=array(
				'prompt'=>$ideaList,
				
				'max_tokens'=>250,
				'temperature'=>0.7,
				
				'top_p'=>1,

				'frequency_penalty'=>0,

				'presence_penalty'=>0,
				
				'best_of'=>1,
				'n'=>1,
				'stream'=>false,
				'logprobs'=>null,
				'stop'=>'\n'
			);
			// $url='https://api.openai.com/v1/engines/davinci/completions';
			$url='https://api.openai.com/v1/engines/curie/completions';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                'Authorization: Bearer '.$config['openAiGPT3']['key'],
				'Content-Type:application/json'
			));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch,CURLOPT_POST, json_encode($fields));
			curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($fields));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			$result=curl_exec($ch);
			// echo $result;
			$result = json_decode($result,true);
			curl_close($ch);

			// $idea=str_replace('Idea: ','',$result['choices'][0]['text']);
			// list($idea,$rest)=explode("\n\n",$idea);
			// $idea=trim($idea);

			echo "\n\n";
			echo json_encode($result);
			echo "\n\n";

			$idea=$result['choices'][0]['text'];

			// echo "\n\n";
			// echo json_encode($idea);
			// echo "\n\n";

			

			$ideas=explode("\n",$idea);

			// go over multiple ideas, except last
			$i=1;
			$ideasFound=0;
			foreach($ideas as $idea){
				if(empty($idea)) continue;
				if(stripos($idea,'bad idea')!=false) continue;
				if($i==count($ideas)) {
					break;
				}
				/* don't take the last idea because it might be a half sentence due to GPT-3 way of answering */

				echo '.';

				if(strlen($idea)<40) {
					echo "No idea found at this time";
					echo "\n\n";
					continue;
				}

				// if(substr($idea,0,strlen('A startup'))!='A startup') {
				// 	exit;
				// }

				if(
					stripos($idea,'____')!==false ||
					stripos($idea,'declutter')!==false ||
					stripos($idea,'closet')!==false ||
					stripos($idea,'get rid')!==false ||
					stripos($idea,'unwanted')!==false
				) {
					echo "____ type or other bad idea, skip";
					echo "\n\n";
					continue;
				}

				// $idea=str_replace('.','',$idea);
				$idea=str_replace('Bad idea: ','',$idea);
				$idea=str_replace('Good idea: ','',$idea);
				$idea=str_replace('Idea: ','',$idea);
				$idea=str_replace('!','',$idea);
				$idea=str_replace('?','',$idea);
				$idea=str_replace('~~','',$idea);
				$idea=str_replace('A blog','A startup',$idea);
				$idea=str_replace('A subscription service','A startup',$idea);
				$idea=str_replace('A website','A startup',$idea);
				$idea=str_replace('A site','A startup',$idea);
				$idea=str_replace('An application','A startup',$idea);
				$idea=str_replace('An app','A startup',$idea);
				$idea=str_replace('iphone/android app','A startup',$idea);
				$idea=str_replace('An online store','A startup',$idea);
				$idea=str_replace('android app','A startup',$idea);
				$idea=str_replace('iphone app','A startup',$idea);
				$idea=str_replace('A service','A startup',$idea);
				$idea=str_replace('A marketplace','A startup',$idea);
				
				$query=$gpt3ideasDb->prepare("SELECT COUNT(*) FROM gpt3ideas WHERE idea=:idea");
				$query->bindValue(':idea',$idea);
				$query->execute();
				$ideaAlreadyExists=$query->fetchAll(PDO::FETCH_ASSOC)[0]["COUNT(*)"];

				if($ideaAlreadyExists) {
					echo "Idea already exists";
					echo "\n\n";
					continue;
				}
				
				$query=$gpt3ideasDb->prepare("SELECT id FROM gpt3ideas ORDER BY id DESC");
				$query->execute();
				$latestId=$query->fetchAll(PDO::FETCH_ASSOC)[0]['id'];
				$id=$latestId+1;

				// <check idea for similarity to existing ideas>
					$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas");
					$query->execute();
					$dbIdeas=$query->fetchAll(PDO::FETCH_ASSOC);
					foreach($dbIdeas as $dbIdea) {
						similar_text($idea,$dbIdea['idea'], $percentage);
						if($percentage>65) { /* the higher percentage is set the more ideas get through */
							echo "Idea already exists";
							echo "\n\n";
							continue(2);
						}
						similar_text($dbIdea['idea'],$idea, $percentage);
						if($percentage>65) {
							echo "Idea already exists";
							echo "\n\n";
							continue(2);
						}
					}
				// </check idea for similarity to existing ideas>

				foreach($bannedIdeas as $bannedIdea) {
					if(stripos($idea,$bannedIdea)!==false) {
						// banned word
						echo 'Banned word';
						echo "\n\n";
						continue(2);
					}
				}

				$idea=trim($idea);

				if(empty($idea)) continue;

				$query=$gpt3ideasDb->prepare("INSERT INTO gpt3ideas(epoch_created,idea,id,votes,upvotes,downvotes) VALUES(:epoch_created,:idea,:id,:votes,:upvotes,:downvotes)");
				$query->bindValue(':epoch_created',time());
				$query->bindValue(':idea',$idea);
				$query->bindValue(':id',$id);
				$query->bindValue(':votes',0);
				$query->bindValue(':upvotes',0);
				$query->bindValue(':downvotes',0);
				$query->execute();

				$ideasFound++;

				echo "Idea: ";
				echo $idea;
				echo "\n";
				echo "\n";

				if($ideasFound>1) break;
			}

			// echo json_encode($result);
		}
	// </generating ideas>




	function generateIdeaTable($ideas,$soloIdea=false,$ideaType=false) {
		global $gpt3votesDb;
		global $bannedIdeas;

		$y=0;
		foreach($ideas as $idea) {
			?><table style="max-width:700px;margin:14px auto;"><?

				foreach($bannedIdeas as $bannedIdea) {
					if(stripos($idea['idea'],$bannedIdea)!==false) {
						// banned word
						continue(2);
					}
				}

				?><tr id="id_<?=$idea['id']?>" class="container"><?
					?><td class="td_idea">
						<span class="idea"><?
						$text=$idea['idea'];
						$i=1;
						echo fixIdeaText($idea['idea']);
						?></span><?
						echo '<br/><span class="time_ago">Generated by GPT-3, '.timeAgoLong($idea['epoch_created']).' ago. </span>';
					
						?><?/*
						<a href="javascript:" class="claim-idea" style="font-size:12px;opacity:0.5;font-weight:normal;">
							Claim this idea
						</a>*/?>
					</td><td class="td_votes">
						<?/*<div class="action-upvote" data-idea-type="<?=$ideaType?>" data-solo="<?=$soloIdea ? 'true' : '';?>" data-id="<?=$idea['id']?>" style="text-decoration:none;font-size:28px;">&nbsp;▲&nbsp;</div>*/?>


						

						<div class="action-downvote" data-idea-type="<?=$ideaType?>" data-solo="<?=$soloIdea ? 'true' : '';?>" data-id="<?=$idea['id']?>" style="text-decoration:none;font-size:28px;">
							<svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1490 1322q0 40-28 68l-136 136q-28 28-68 28t-68-28l-294-294-294 294q-28 28-68 28t-68-28l-136-136q-28-28-28-68t28-68l294-294-294-294q-28-28-28-68t28-68l136-136q28-28 68-28t68 28l294 294 294-294q28-28 68-28t68 28l136 136q28 28 28 68t-28 68l-294 294 294 294q28 28 28 68z"/></svg>
						</div>

						<div class="votes" data-votes="<?=$idea['votes']?>"><?
							echo number_format($idea['votes']);
						?></div>

						<div class="action-upvote" data-idea-type="<?=$ideaType?>" data-solo="<?=$soloIdea ? 'true' : '';?>" data-id="<?=$idea['id']?>" style="text-decoration:none;font-size:28px;">
							<svg aria-hidden="true" width="35" focusable="false" data-icon="heart" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M462.3 62.6C407.5 15.9 326 24.3 275.7 76.2L256 96.5l-19.7-20.3C186.1 24.3 104.5 15.9 49.7 62.6c-62.8 53.6-66.1 149.8-9.9 207.9l193.5 199.8c12.5 12.9 32.8 12.9 45.3 0l193.5-199.8c56.3-58.1 53-154.3-9.8-207.9z"></path></svg>
						</div>	


						<?/*<div class="action-downvote" data-idea-type="<?=$ideaType?>" data-solo="<?=$soloIdea ? 'true' : '';?>" data-id="<?=$idea['id']?>" style="text-decoration:none;font-size:28px;">&nbsp;▼&nbsp;</div>*/?>

						<?
					?></td><?
				?></tr><?
				$y++;
			?></table><?
		}
	}
	function fixIdeaText($idea) {
		return $idea;
		//
		$idea=str_replace('Idea: ','',$idea);
		// rewrites idea text a bit to remove startup etc.
		$idea=str_ireplace('Bad idea: ','',$idea);
		$idea=str_ireplace('Good idea: ','',$idea);
		$idea=str_ireplace('Idea: ','',$idea);
		$idea=str_ireplace('!','',$idea);
		$idea=str_ireplace('?','',$idea);
		$idea=str_ireplace('~~','',$idea);
		$idea=str_ireplace('A blog','A startup',$idea);
		$idea=str_ireplace('A subscription service','A startup',$idea);
		$idea=str_ireplace('A website','A startup',$idea);
		$idea=str_ireplace('A site','A startup',$idea);
		$idea=str_ireplace('A way to','A startup that',$idea);
		$idea=str_ireplace('A startup that has a service to ','A startup that ',$idea);
		$idea=str_ireplace('A platform','A startup',$idea);
		$idea=str_ireplace('A software product','A startup',$idea);
		$idea=str_ireplace('A product','A startup',$idea);
		$idea=str_ireplace('An application','A startup',$idea);
		$idea=str_ireplace('An app','A startup',$idea);
		$idea=str_ireplace('iphone/android app','A startup',$idea);
		$idea=str_ireplace('An online store','A startup',$idea);
		$idea=str_ireplace('android app','A startup',$idea);
		$idea=str_ireplace('iphone app','A startup',$idea);
		$idea=str_ireplace('A service','A startup',$idea);
		$idea=str_ireplace('A marketplace','A startup',$idea);
		$idea=str_ireplace('This startup is building ','',$idea);
		$idea=str_ireplace('A startup building ','',$idea);
		$idea=str_ireplace('A startup that is building','',$idea);
		$idea=str_ireplace('A startup that ','',$idea);
		$idea=str_ireplace('This company is building ','',$idea);
		$idea=str_ireplace('This company is building ','',$idea);

		$words=explode(' ',$idea);

		$newIdea='';
		$i=1;
		foreach($words as $word) {
			if($i==1) {
				if($word=='This') {
					//
				}
				else if($word=='is') {
					//
				}
				else if(substr($word,-8)=='provides') {
					$newIdea=$newIdea.ucfirst(substr($word,0,strlen($word)-1)).' ';
				}
				else if(substr($word,-2)=='es') {
					$newIdea=$newIdea.ucfirst(substr($word,0,strlen($word)-2)).'e ';
				}
				else if(substr($word,-1)=='s') {
					$newIdea=$newIdea.ucfirst(substr($word,0,strlen($word)-1)).' ';
				}
				else {
					// echo ucwords($word);
				}
			}
			else {
				$newIdea=$newIdea.$word.' ';
			}
			$i++;
		}

		// $newIdea=str_replace('A service for ','',$newIdea);
		// $newIdea=str_replace('A service that ','',$newIdea);
		// $newIdea=str_replace('Want to ','',$newIdea);
		// $newIdea=str_replace('Provide ','',$newIdea);
		// $newIdea=str_replace('Offer ','',$newIdea);
		// $newIdea=str_replace('Help people ','',$newIdea);

		$newIdea = preg_replace('/\xc2\xa0/', ' ', $newIdea);
		$newIdea = trim($newIdea);


		return ucfirst($newIdea);
	}

	function getRandomIdea() {
		global $gpt3ideasDb;
		global $gpt3votesDb;
		global $newUser;

		$try=0;
		$maxTries=10;
		$foundRandomIdeaNotVotedOnYet=false;
		mt_srand(time());
		while(!$foundRandomIdeaNotVotedOnYet) {
			if(mt_rand(0,1)==1) {
				// show random in last 24 hours
				$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND epoch_created>".strtotime("-7 days")." ORDER BY RANDOM() ASC LIMIT 1");
				$query->bindValue(':id',$_GET['id']);
				$query->execute();
				$randomIdea=$query->fetchAll(PDO::FETCH_ASSOC);
			}
			else {
				// show random idea
				$query=$gpt3ideasDb->prepare("SELECT * FROM gpt3ideas WHERE id IS NOT :id AND claimed IS NOT 1 AND human_seeded IS NOT 1 AND votes>=0 ORDER BY RANDOM() ASC LIMIT 1");
				$query->bindValue(':id',$_GET['id']);
				$query->execute();
				$randomIdea=$query->fetchAll(PDO::FETCH_ASSOC);
			}

			if(empty($randomIdea[0]['idea'])) {
				continue;
			}
			
			// <banned idea>
				foreach($bannedIdeas as $bannedIdea) {
					if(stripos($randomIdea[0]['idea'],$bannedIdea)!==false) {
						continue(2);
					}
				}
			// </banned idea>


			$query=$gpt3votesDb->prepare("SELECT COUNT(*) FROM gpt3votes WHERE user_id=:user_id AND id=:id");
			$query->bindValue(':id',$randomIdea[0]['id']);
			$query->bindValue(':user_id',$_COOKIE['ideasai_user_id']);
			$query->execute();
			$alreadyVotedOnThis=$query->fetchAll(PDO::FETCH_ASSOC)[0]['COUNT(*)'];

			// $randomIdea[0]['idea']=$alreadyVotedOnThis.' '.$randomIdea[0]['id'].' '.$_COOKIE['ideasai_user_id'].' '.$randomIdea[0]['idea'];

			if(!$alreadyVotedOnThis) {
				$foundRandomIdeaNotVotedOnYet=true;
			}
			else {
				continue;
			}


			$try++;

			if($try>$maxTries) {
				echo "No idea found";
				exit;
			}

		}
		return $randomIdea;
	}
	function loadDbs($dbs) {
		try {
			foreach($dbs as $db) {
				global ${$db.'Db'};

				// <load cities db>
					${$db.'DbFile'}=__DIR__.'/../data/'.$db.'.db';
					if(!file_exists(${$db.'DbFile'})) {
						echo ${$db.'DbFile'};
						echo ' does not exist';
					}
					// if old undeleted journal file found, delete it because it locks the db for writing
					if(file_exists(${$db.'DbFile'}.'-journal') && filemtime(${$db.'DbFile'}.'-journal')<strtotime("-5 minutes")) {
						rename(${$db.'DbFile'}.'-journal',${$db.'DbFile'}.'-journal_'.date('Y-m-d-H-i-s'));
					}
					${$db.'Db'} = new PDO('sqlite:/'.${$db.'DbFile'}) or die("Cannot open the database");
					${$db.'Db'}->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
					${$db.'Db'}->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					// echo "\n\n";
					// echo $db.'Db';
					// echo "\n\n";
					// print_r(${$db.'Db'});
					// echo "\n\n";
				// </load cities db>
			}
		}
		catch ( PDOException $e ) {
			echo 'ERROR!';
			print_r( $e );
		}
	}
	function generateRandomString($length = 10) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	function sortBySubkeyFast(&$array, $subkey="id", $sort_ascending=false) {
		if($sort_ascending==='desc') {
		 	$sort_ascending=false;
		} else if($sort_ascending==='asc') {
		 	$sort_ascending=true;
		}
		
		usort($array, function ($a, $b) use ($subkey) {
			 if ($a[$subkey] == $b[$subkey]) return 0;
			 return ($a[$subkey] < $b[$subkey]) ? -1 : 1;
		 });

		 if(!$sort_ascending) $array = array_reverse($array);
	}
	function timeAgoShort($ptime)
	{

		if($ptime>time()) {
			// in future, so reverse it
			$etime = $ptime - time();
		}
		else {
			$etime = time() - $ptime;
		}

		if ($etime < 1)
		{
			return '0d';
		}

		$a = array( 12 * 30 * 24 * 60 * 60  =>  'yr',
					30 * 24 * 60 * 60	   =>  'mo',
					24 * 60 * 60			=>  'd',
					60 * 60				 =>  'h',
					60						=>  'min',
					1						=>  's'
					);

		foreach ($a as $secs => $str)
		{
			$d = $etime / $secs;
			if ($d >= 1)
			{
				$r = round($d);
				return $r . '' . $str . ($r > 1 ? '' : '') . '';
			}
		}
	}
	function timeAgoLong($ptime)
	{
	  
	  	if($ptime>time()) {
			// in future, so reverse it
			$etime = $ptime - time();
		}
		else {
			$etime = time() - $ptime;
		}

		if ($etime < 1)
		{
			return 'one day';
		}

		$a = array( 12 * 30 * 24 * 60 * 60  =>  'year',
					30 * 24 * 60 * 60	   =>  'month',
					24 * 60 * 60			=>  'day',
					60 * 60				 =>  'hour',
					60						=>  'minute',
					1						=>  'second'
					);

		foreach ($a as $secs => $str)
		{
			$d = $etime / $secs;
			if ($d >= 1)
			{
				$r = floor($d);
				return $r . ' ' . $str . ($r > 1 ? 's' : '');
			}
		}
	}

	// <saveShellParametersInGET>
		// Checks for command line parameters (e.g. -- 'channelID=x') and saves them as $_GET['channelID']='x'.
		// This creates the ability to send parameters through both command line and web
		function saveShellParametersInGET() {
			global $argv;
			global $_GET;
			if(!empty($argv)) {
				foreach($argv as $parameter) {
					$parameterParts=explode('=',$parameter);
					if(
							!empty($parameterParts[0])
						&& 
							!empty($parameterParts[1])
					) {
						$parameterName=$parameterParts[0];
						$parameterValue=$parameterParts[1];
						$_GET[$parameterName]=$parameterValue;
					}
				}
			}
		}
	// </saveShellParametersInGET>
	
	function sendToAdminTelegram($message) {
		global $config;
		$message='ideasai '.' '.$_SERVER["SCRIPT_NAME"].' '.__FILE__.' '.$message;
		file_get_contents('https://api.telegram.org/bot'.$config['telegramAdminChat']['bot_token'].'/sendMessage?chat_id='.$config['telegramAdminChat']['chat_id'].'&text='.urlencode($message).'&disable_web_page_preview=true');
	}
?>
