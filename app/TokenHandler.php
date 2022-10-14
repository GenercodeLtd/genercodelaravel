<?php
namespace GenerCodeLaravel;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Symfony\Component\HttpFoundation\Cookie;

class TokenHandler
{
    private $refresh_minutes = 86400;
    private $auth_minutes = 15;
 

    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }
    }

    public function setConfigs($configs)
    {
        $arr = ["expire_minutes", "refresh_minutes"];
        foreach ($arr as $key) {
            if (isset($configs[$key])) {
                $this->$key = $configs[$key];
            }
        }
    }


    function decode($value) {
        $pts = explode(":", $value);
        return ["u"=>$pts[0], "i"=>$pts[1]];
    }


    function encode($user, $id) {
        return $user . ":" . $id;
    }


    public function save(string $profile_type, int $id)
    {
        $payload = $this->encode(["u"=>$profile_type, "i"=>$id]);

        return response()
        ->withCookie(cookie("api-auth", $payload, $refresh_minutes))
        ->withCookie(cookie("api-refresh", $payload, $auth_minutes));
    }


    public function switchTokens(Request $request)
    {
        $refresh = cookie("api-refresh");
        if ($refresh) {
            $auth = cookie("api-auth");

            return response()
            ->withCookie(cookie("api-auth", $refresh->getValue(), $refresh_minutes));
        } else {
            return response(json_encode(""), 403);
        }
    }



    public function loginFromToken($token, $profile) {
        $payload = $this->decode($token);
        if ($payload->u == $profile) {
            $cookie_expires = time() + 86400; //24 hours update
            $cookies = [];

            $access_token = $this->encode(["u"=>$payload->u, "i"=>$payload->i], $this->auth_minutes);
            $refresh_token = $this->encode(["u"=>$payload->u, "i"=>$payload->i], $this->refresh_minutes);

            $cookies[] = $this->createCookie("api-auth", $access_token, $cookie_expires);
            $cookies[] = $this->createCookie("api-refresh", $refresh_token, $cookie_expires);

            return response()
            ->withCookie(cookie("api-auth", $payload, $refresh_minutes))
            ->withCookie(cookie("api-refresh", $payload, $auth_minutes));
        } else {
            return response(json_encode(""), 401);
        }
    }


    public function logout()
    {
        \Cookie::queue(\Cookie::forget('api-auth'));
        \Cookie::queue(\Cookie::forget('api-refresh'));
    }
}