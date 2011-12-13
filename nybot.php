<?php
session_start();
set_time_limit(0);
ini_set('display_errors',0);

	/*
	*      @author: JustVanBlooM.de
        *      @email: nx3d@me.com
        *		DONATE: 1F9BrpcuTx49BmpFEna7YiA3ZUjzVUhUjk
        *      @name: nybot v2.08
        *      @desc: calculates values automatically, sells and buys bitcoins and will stop if the market crashes
        *
        *      Set your USERNAME and PASSWORD + API keys and your ready to trade with my bot!
        *
        *      Parameter:
        *      &stop=1 : script will die
        *      &risk=1 : will sell 8/10 bitcoins
        *      &rate=X : manipulate your rate manually
        *
        *              ....it was fun and maybe a small cash injection....
        *				
        */
     
            static $rate = 2; //0
            $user = "USERMANE";
            $pass = "PASSWORD";
            /* US$ limit, script will sell nearly every bitcoin if one limit is reached */
            $downwardLimit = 2;
            $upwardLimit = 1000;
            $refreshtime = 30000;

			/* ------API------ */
			function mtgox_query($path, array $req = array()) {
				// API settings
				$key = 'YOUR-API-KEY';
				$secret = 'YOUR-API-SECRET';
				$mt = explode(' ', microtime());
				$req['nonce'] = $mt[1].substr($mt[0], 2, 6);
 				$post_data = http_build_query($req, '', '&');
 				$headers = array(
					'Rest-Key: '.$key,
					'Rest-Sign: '.base64_encode(hash_hmac('sha512', $post_data, base64_decode($secret), true)),
				);
 				static $ch = null;
				if (is_null($ch)){
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MtGox PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
				}
				curl_setopt($ch, CURLOPT_URL, 'https://mtgox.com/api/'.$path);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_TIMEOUT, 5);
				$res = curl_exec($ch);
				if ($res === false){throw new Exception('Could not get reply: '.curl_error($ch));}
				$dec = json_decode($res, true);
				if (!$dec){throw new Exception('Invalid data received, please make sure connection is working and requested API exists');}
				return $dec;
			}
 
			$bal=mtgox_query('0/getFunds.php');
			$bal[usds]=$bal[usds];  //balance usds
			$bal[btcs]=$bal[btcs];  //balance bitcoins

            function duration($secs){
                $vals = array('w' => (int) ($secs / 86400 / 7),
                'd' => $secs / 86400 % 7,
                'h' => $secs / 3600 % 24,
                'm' => $secs / 60 % 60,
                's' => $secs % 60);
                $ret = array();
                $added = false;
                foreach ($vals as $k => $v){if ($v > 0 || $added){$added = true;$ret[] = $v . $k;}}
                return join(' ', $ret);
            }
			
			/* ------Vars------ */
            if(!isset($_GET["stop"])) $_GET["stop"] = false;
            if(!isset($_GET["risk"])) $_GET["risk"] = false;
            if(!isset($_GET["reset"])) $_GET["reset"] = false;
            if(!isset($_GET["rate"])) $_GET["rate"] = 0;
            if(!isset($_SESSION['sold'])) $_SESSION['sold'] = false;
            $_SESSION['riskcounter'] = 0;
            $_SESSION['wasrisky'] = false;
            $_SESSION['riskrate'] = 0;
            $_SESSION['riskrounds'] = 5;
             if(!isset($_SESSION['buyOrNot'])) $_SESSION['buyOrNot'] = 0;
            if(!isset($_SESSION['buyOrNotActive'])) $_SESSION['buyOrNotActive'] = false;
            if(!isset($_SESSION['buyOrNotCounter'])) $_SESSION['buyOrNotCounter'] = 5;
            if(!isset($_SESSION['buyitnow'])) $_SESSION['buyitnow'] = false;
            if(!isset($_SESSION['earndiff'])) $_SESSION['earndiff'] = 0;
            if(!isset($_SESSION['earnedSeller'])) $_SESSION['earnedSeller'] = 0;
            if(!isset($_SESSION['lastrate'])) $_SESSION['lastrate'] = 0;
            if(!isset($_SESSION['ratiodiff'])) $_SESSION['ratiodiff'] = 0;
            if(!isset($_SESSION['newamount'])) $_SESSION['newamount'] = 0;
            if(!isset($_SESSION['oldamount'])) $_SESSION['oldamount'] = 0;
            if(!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
			$tickerJSON = (mtgox_query('0/data/ticker.php'));
			$data = $tickerJSON[ticker];
			$high = $data[high];
			$low = $data[low];
			$avg  = $data[avg];
			$vol = $data[vol];
			$last = $data[last];
			$buy = $data[buy];
			$sell = $data[sell];
            $buy = round($buy,3);
            $sell = round($sell,3);
            $amountbc = $bal[btcs];
            $sellamountE = round($amountbc/9,2);
            $sellamountW = round($amountbc/2.5,2);
            $sellprice = round($sell + 0.06,2);
            $amountmoneyS = $bal[usds];
            $amountmoney = round($amountmoney[0] - ($amountmoney[0] / 7),2);
            $buyprice = round($buy - 0.05,2);
            if($amountbc < 5 && $_SESSION['sold'] != true){$wanted = $rate + 2.5;
				}else if($amountbc > 5 && 8 > $amountbc && $_SESSION['sold'] != true){$wanted = $rate + 2;
				}else if($amountbc > 8 && 11 > $amountbc&& $_SESSION['sold'] != true){$wanted = $rate + 1.5;
				}else if($amountbc > 11 && $_SESSION['sold'] != true){$wanted = $rate + 1.5;
			}
            $curdiff = $sell - $rate;
            $diff = $wanted - $sell;
            $earned = ($sell - $rate)*$amountbc;
            $earnedEur = $earned * 0.69;
            if(!isset($_GET["showEur"])) $_GET["showEur"] = false;
            $timestamp = time();
            $timestampStarted = 1323777537;
            $timediff = $timestamp - $timestampStarted;
            $date = date("d.m.Y",$timestamp);
            $time = date("H:i",$timestamp);
            $dateStarted = date("d.m.Y",$timestampStarted);
            $timeStarted = date("H:i",$timestampStarted);
            if($_SESSION['lastrate'] != $sell){
                    $_SESSION['earnedSeller'] = round($earned + $curdiff + $_SESSION['ratiodiff'],4);
			}
            $risksellbc = round($amountbc / 10 * 8, 2);
            $risksell = $sell;
            if($_GET["stop"] == true){die("Botstop");}
            if($_GET["reset"] == true){$_SESSION['riskcounter'] = 0;}
            if($_GET["risk"] == true){mtgox_query('0/sellBTC.php', array('amount' => $risksellbc, 'price' => $risksell));}
            if($_GET["rate"] != 0){$rate = $_GET["rate"];$_SESSION['sold'] = false;}
            if(($amountbc * $sell) + $amountmoneyS < $downwardLimit || ($amountbc * $sell) + $amountmoneyS >= $upwardLimit){
				mtgox_query('0/sellBTC.php', array('amount' => $amountbc, 'price' => $sell));			
                echo "Sold $amountbc BitCoins at a price of $sell: ".$amountbc*$sell." $<br>";
                die;
            }
			
			/* ------Trade------ */
            /* -------LOSS------ */
            if($_SESSION['riskcounter'] >= 7){
                $_SESSION['sold'] = true;
                $_SESSION['riskcounter'] = 0;
                $_SESSION['wasrisky'] = true;   
				mtgox_query('0/sellBTC.php', array('amount' => $risksellbc, 'price' => $risksell));			
                echo "<center>Sold $risksellbc BitCoins at a price of $risksell: ".$risksellbc*$risksell." $<br></center>";
            }
           
            /* -------WIN------- */
            else if($_SESSION['earnedSeller'] >= 3 && $_SESSION['sold'] = false){
                $_SESSION['ratiodiff'] += $_SESSION['earnedSeller'];
                $_SESSION['sold'] = true;
                $_SESSION['riskcounter'] = 0;
				mtgox_query('0/sellBTC.php', array('amount' => $sellamountE, 'price' => $sellprice));			
                echo "<center>Sold $sellamountE BitCoins at a price of $sellprice: ".$sellamountE*$sellprice." $<br></center>";
            }
			
            /* -------LOSS------ */
            else if($_SESSION['earnedSeller'] <= -3){
                $_SESSION['ratiodiff'] += $_SESSION['earnedSeller'];
                $increase = round($_SESSION['earnedSeller'],0);
                if($increase > $_SESSION['earnedSeller']){$increase--;}
                if($increase % 2 != 0 && $increase >= 2){$increase -= 0.5;}
                if($increase < 1){$increase = 2;$_SESSION['riskcounter'] += ($increase / 2);}
                if($_SESSION['riskcounter'] > 7) $_SESSION['riskcounter'] = 7;
            }
     
            /* -------WIN------- */
            if($wanted <= round($sell,1)){
                $_SESSION['sold'] = true;
                $_SESSION['riskcounter'] = 0;
				mtgox_query('0/sellBTC.php', array('amount' => $sellamountW, 'price' => $sellprice));			
                echo "<center>Sold $sellamountW BitCoins at a price of $sellprice: ".$sellamountW*$sellprice." $<br></center>";
            }
           
            /* -------LOSS------ */
            if($_SESSION['wasrisky'] == true){
                /* uh.... */
                echo "riskrounds";
                if($_SESSION['lastrate'] != $sell){$_SESSION['riskrate'] += (round($sell,3) - $rate);}
                if($_SESSION['riskrounds'] == 0){
                    if($_SESSION['riskrate'] < 0.3){
                        $_SESSION['riskrounds']++;
                    }else{
                        $_SESSION['riskrate'] = 0;
                        $_SESSION['riskrounds'] = 6;
                        $_SESSION['wasrisky'] = false;
                    }
                }
                $_SESSION['riskrounds']--;
            }
            
			/* -------WIN------- */
            if($_SESSION['riskcounter'] < 7 && $_SESSION['wasrisky'] == false){
                if($_SESSION['buyOrNotCounter'] == 0){
                    $_SESSION['buyOrNot'] = (round($sell,3) - $rate);
                    $_SESSION['buyitnow'] = true;
                    if($_SESSION['buyOrNot'] < ($amountbc / 100)){
                        $_SESSION['buyitnow'] = false;
					}
                    $_SESSION['buyOrNotCounter']  = 5;
                    $_SESSION['buyOrNotActive'] = false;
                    $_SESSION['buyOrNot'] = 0;
                }else{
					if($_SESSION['buyOrNot'] == 0 && $_SESSION['buyOrNotActive'] == false){
						$_SESSION['buyOrNot'] = (round($sell,3) - $rate);
						$_SESSION['buyOrNotActive'] = true;
					}else if($_SESSION['buyOrNotActive'] == true){
						if($_SESSION['lastrate'] != $sell){
							$_SESSION['buyOrNot'] = (round($sell,3) - $rate);
						}
					}
					if($curdiff > 0){
						$_SESSION['buyOrNotCounter']--;
					}
                }
                if($_SESSION['oldamount'] >= $_SESSION['newamount'] && $_SESSION['oldamount'] != 0 && $_SESSION['newamount'] != 0){
					if($_SESSION['attempts'] < 5){
						$_SESSION['attempts']++;
                        $_SESSION['buyitnow'] = true;
                        $buyprice = round($buy - 0.05 - ($_SESSION['attempts'] * 0.01),2);
                    }
                }
                   
                /* buying */
                if($_SESSION['buyitnow'] == true){
                    $_SESSION['oldamount'] = 0;
                    $_SESSION['newamount'] = 0;
                    $buyamount = round($amountmoney / $buyprice, 2);
                    if($buyamount < 0.1){$buyamount = 0;}
					if($buyamount > 0){
                        $buyamount += $buyamount * 0.0065;
                        while($buyamount * $buyprice > $amountmoney)
                        $buyamount -= 0.1;
                        $buyamount = round($buyamount, 2);
                        $boughtdata = PostToHost("mtgox.com","/code/buyBTC.php",0,"name=$user&pass=$pass&amount=$buyamount&price=$buyprice");
						mtgox_query('0/buyBTC.php', array('amount' => $buyamount, 'price' => $buyprice));			
                        $boughtdata = explode(":",$boughtdata);
                        $boughtdata = explode("for ",$boughtdata[19]);
                        $boughtdata = explode(".\"]",$boughtdata[1]);
                        $boughtdata = $boughtdata[0];
                        $_SESSION['oldamount'] = $amountbc;
                        $_SESSION['newamount'] = $amountbc + $buyamount;
                        echo "<center>Bought $buyamount BitCoins at a price of $buyprice: ".$buyamount*$buyprice." $<br></center>";
                        $ratingAfter = $buyamount * 100 / ($buyamount + $amountbc) / 100;
                        $ratingAfter = $ratingAfter * ($buyprice - $rate);
                        $rate += $ratingAfter;
                        $rate = round($rate, 3);
                    }
                    $_SESSION['buyOrNotCounter'] = 5;
                    $_SESSION['buyOrNotActive'] = false;
                    $_SESSION['buyOrNot'] = 0;
                }
            }
           
            $_SESSION['lastrate'] = $sell;
            $hours = 0;
            if(($timediff / 3600 % 24) > 0){$hours += ($timediff / 3600 % 24);}
            if(($timediff / 86400 % 7) > 0){$hours += (($timediff / 86400 % 7) * 24);}
            if($hours == 0){$hours = 1;}
           
            echo "<table align=center>";
                    echo "<script language='javascript' type='text/javascript'>setTimeout('location.reload();',$refreshtime);</script>";
                    echo "<colgroup width = 250></colgroup>";
                    echo "<tr align = left><th>Type</th><th>Value per BC</th></tr>";
                    echo "<tr><td>Rate</td><td>$rate $</td></tr>";
                    echo "<tr><td>Current stock</td><td>$amountbc BitCoins</td></tr>";
                    echo "<tr><td>Money (bank)</td><td>$amountmoneyS $</td></tr>";
                    echo "<tr><td>Money (market)</td><td>".round($amountbc * $sell,3)." $</td></tr>";
                    echo "<tr><td>Money (overall)</td><td>".(round($amountbc * $sell,3)+$amountmoneyS)." $</td></tr>";
                    echo "<tr><td>Current</td><td>".round($sell,3)." $</td></tr>";
                    echo "<tr><td>Rate Difference</td><td>".round($curdiff,3)." $</td></tr>";
                    echo "<tr><td>Peak</td><td>$high $</td></tr>";
                    echo "<tr align = left><th>Type</th><th>Time</th></tr>";
                    echo "<tr><td>Started</td><td>$dateStarted - $timeStarted</td></tr>";
                    echo "<tr><td>Now</td><td>$date - $time</td></tr>";
                    echo "<tr align = left><th>Type</th><th>Value</th></tr>";
                    echo "<tr><td><h2>Elapsed</h2></td><td><h2>".duration($timediff)."</h2></td></tr>";
     
                    if($earned > 0){
						echo "<tr><td><h2>Earned $</h2></td><td><h2><font color=\"green\">".round($earned + ($amountbc * $sell) + $amountmoneyS,3)." $</font></h2></td></tr>";
						echo "<tr><td><h2>$ / hour</h2></td><td><h2><font color=\"green\">".round($earned / $hours,3)." $</font></h2></td></tr>";
					}else{
						echo "<tr><td><h2>$ / hour</h2></td><td><h2><font color=\"red\">".round($earned / $hours,3)." $</font></h2></td></tr>";
					}
                   
                    if($_GET["showEur"] == true){
                        if($earned > 0){
							echo "<tr><td>-</td><td>-</td></tr>";
							echo "<tr><td><h2>Earned ".chr(128)."</h2></td><td><h2><font color=\"green\">".round($earnedEur,3)." ".chr(128)."</font></h2></td></tr>";
                        }else{
							echo "<tr><td>-</td><td>-</td></tr>";
							echo "<tr><td><h2>Earned ".chr(128)."</h2></td><td><h2><font color=\"red\">".round($earnedEur,3)." ".chr(128)."</font></h2></td></tr>";
				        }
					}
                           
                    if($_GET["showEur"] == true){
                            if($earned > 0){
                    echo "<tr><td><h2>".chr(128)." / hour</h2></td><td><h2><font color=\"green\">".round($earnedEur / $hours,3)." ".chr(128)."</font></h2></td></tr>";
                            }else{
						echo "<tr><td><h2>".chr(128)." / hour</h2></td><td><h2><font color=\"red\">".round($earnedEur / $hours,3)." ".chr(128)."</font></h2></td></tr>";
						}
				    }
                           
                    echo "<tr><td>Buy Ratio</td><td>".round($_SESSION['buyOrNot'],3)."</td></tr>";
                    echo "<tr><td>Buying Steps left</td><td>".$_SESSION['buyOrNotCounter']." / 5 </td></tr>";
                    if($_SESSION['riskcounter'] > 0){
						echo "<tr><td>Riskcounter</td><td><font color=\"red\">".$_SESSION['riskcounter']." / 7</font></td></tr>";
                    }else{
						echo "<tr><td>Riskcounter</td><td>".$_SESSION['riskcounter']." / 7 </td></tr>";
					}
				    echo "<tr><td>Riskrate</td><td>".$_SESSION['riskrate']."</td></tr>";
					echo "<tr><td>NYBOT_2 - mtgoxbot </td><td> from JustVanBlooM</td></tr>";


            echo "</table>";
            echo "<input type=\"hidden\" name=\"PHPSESSID\" value=\"<?=session_id()?>\">";
?>
