<?php

use WebreatheTest\app\WebreatheTestSession as ISession;
use WebreatheTest\app\WebreatheTestModule as IModule;

/**
 * Get json filte datas
 * @param string $files files path
 * @return (array | bool)
 */
function getJsonFileData($files)
{
    if (file_exists($files)) {
        $arr = file_get_contents($files);
        $arr = json_decode($arr);
        return $arr;
    }

    return false;
}

/**
 * Convert object to array.
 *
 * @param  object $object Convert value.
 * @return array $arr Result Value.
 */
function convertObjectToArray($object)
{

    (is_object($object)) ? $arr = (array) $object : $arr = $object;

    foreach ($arr as $key => $value) {
        if (is_object($value)) {
            $arr[ $key ] = convertObjectToArray($value);
        }
    }

    return $arr;
}

function createNonceSecurity()
{
    $nonce = '';
    for ($i = 0; $i < 13; $i++) {
        $nonce .= random_int(0, 9);
    }

    $activeNonce = ISession::get('nonce_security', 2);
    $activeNonceTime = ISession::get('nonce_time', 2);

    if (! is_array($activeNonce)) {
        $activeNonce = array();
    }

    if (! is_array($activeNonceTime)) {
        $activeNonceTime = array();
    }

    $expire_day = strtotime('+1 day', strtotime(date("Y-m-d")));

    $activeNonceTime[$nonce] = $expire_day;

    array_push($activeNonce, $nonce);

    ISession::set('nonce_security', $activeNonce, 2);
    ISession::set('nonce_time', $activeNonceTime, 2);
    return $nonce;
}

function verifyNonceSecurity($nonce)
{
    $activeNonce = ISession::get('nonce_security', 2);
    if (is_array($activeNonce)) {
        if (in_array($nonce, $activeNonce)) {
            $activeNonceTime = ISession::get('nonce_time', 2);
            if (isset($activeNonceTime[$nonce]) && is_array($activeNonceTime))
            {
                $dateNow = strtotime(date("Y-m-d"));
                if ($dateNow > $activeNonceTime[$nonce]) {
                    return false;
                } else {
                    return true;
                }
            }
        }
    }

    return false;
}

function set_cookies($key, $value, $options = array())
{
    global $WPD_SESSION;
    if (! headers_sent()) {
        $exipation_time = time() + 120 * 3600; // Expire dans 120 heures.
        $root_url = $_SERVER['SERVER_PROTOCOL']; // Récupération de l'url de base du site.
        $secure =  strpos($root_url, 'https'); // Vérifie si le protocole utilisé est http ou https.
        $value = serialize($value);
        $name = md5($key);
        $domaine = (defined(COOKIE_DOMAIN)) ? COOKIE_DOMAIN : '/';
        $path = COOKIEPATH ? COOKIEPATH : '';

        $option = array(
            'expires'  => $exipation_time,
            'secure'   => $secure,
            'path'     => $path,
            'domain'   => $domaine,
            'httponly' => false,
        );

        if (! isset($_COOKIE[$name])) {
            if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
                setcookie($name, $value, $option);
            } else {
                setcookie($name, $value, $exipation_time, $path, $domaine, $secure, false);
            }
        } else {
            $found = get_cookies($key, $options);

            if (is_array($found)) {
                $found = $value;
                $_COOKIE[$name] = $found;
            }
        }
    } else {
        trigger_error("WebreatheTest: " . $name . " Header is send");
    }
}

function get_cookies($name, $options = array())
{
    $imo_cookie_name = md5($name);

    if (isset($_COOKIE[ $imo_cookie_name ])) {
        $found = unserialize($_COOKIE[ $imo_cookie_name ]);
        if (count($options) > 0) {
            foreach ($options as $value) {
                if (isset($found[ $value ])) {
                    $found = $found[ $value ];
                } else {
                    $fond = false;
                }
            }
        }

        return $found;
    }

    return false;
}


function delete_cookies($name, $options = array())
{
    $imo_cookie_name = md5($name);

    if (isset($_COOKIE[ $imo_cookie_name ])) {
        $found = unserialize($_COOKIE[ $imo_cookie_name ]);
        if (count($options) > 0) {
            $_COOKIE[ $imo_cookie_name ] = get_cookies($name, $options);
            return true;
        }

        unset($_COOKIE[ $imo_cookie_name ]);
        return true;
    }

    return false;
}

function getModuleData()
{
    global $site_data;
    $db = $site_data['db']->getDb();
    
    $query = 'INSERT INTO module(desc_module, date_creation_module, date_utilisation)
    VALUES(:desc_module, :date_creation_module, :date_utilisation)';
    $request = $db->prepare($query);
   
    $moduleData = array(
        'temperature' => IModule::getTemperature(),
        'vitesse'     => IModule::getVitesse(),
        'nombreDonne' => IModule::getTailleDonne(),
    );

    $request->execute([
        ':desc_module' => serialize($moduleData),
        ':date_creation_module' => date("Y-m-d"),
        ':date_utilisation' => date("Y-m-d")
    ]);

    echo json_encode($moduleData);
}