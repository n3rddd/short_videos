<?php
/**
*@Author: JH-Ahua
*@CreateTime: 2025/6/17 下午5:00
*@email: admin@bugpk.com
*@blog: www.jiuhunwl.cn
*@Api: api.bugpk.com
*@tip: 抖音视频图集去水印解析
*/
header("Access-Control-Allow-Origin: *");
header('Content-type: application/json');
function douyin($url)
{

    // 构造请求数据
    $header = array('User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1 Edg/122.0.0.0');
    // 尝试从 URL 中获取视频 ID
    $id = extractId($url);
    // 检查 ID 是否有效
    if (empty($id)) {
        return array('code' => 400, 'msg' => '无法解析视频 ID');
    }

    // 发送请求获取视频信息
    $response = curl('https://www.iesdouyin.com/share/video/' . $id, $header);
    $pattern = '/window\._ROUTER_DATA\s*=\s*(.*?)\<\/script>/s';
    preg_match($pattern, $response, $matches);

    if (empty($matches[1])) {
        return array('code' => 201, 'msg' => '解析失败');
    }

    $videoInfo = json_decode(trim($matches[1]), true);
    if (!isset($videoInfo['loaderData'])) {
        return array('code' => 201, 'msg' => '解析失败');
    }
    //替换 "playwm" 为 "play"
    $videoResUrl = str_replace('playwm', 'play', $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['video']['play_addr']['url_list'][0]);

    $imgurljson = $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['images'];
    $imgurl = [];
    if (is_array($imgurljson) && isset($imgurljson[0])) {
        // 遍历 JSON 数组
        foreach ($imgurljson as $item) {
            // 检查当前元素是否包含 url_list 标签
            if (isset($item['url_list']) && is_array($item['url_list']) && count($item['url_list']) > 0) {
                // 将 url_list 的第一个值添加到 $imgurl 数组中
                $imgurl[] = $item['url_list'][0];
            }
        }
    }
    // 构造返回数据
    $arr = array(
        'code' => 200,
        'msg' => '解析成功',
        'data' => array(
            'author' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['author']['nickname'],
            'uid' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['author']['unique_id'],
            'avatar' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['author']['avatar_medium']['url_list'][0],
            'like' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['statistics']['digg_count'],
            'time' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]["create_time"],
            'title' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['desc'],
            'cover' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['video']['cover']['url_list'][0],
            'images' => $imgurl,
            'url' => count($imgurl) > 0 ? '当前为图文解析，图文数量为:' . count($imgurl) . '张图片' : $videoResUrl,
            'music' => array(
                'title' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['music']['title'],
                'author' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['music']['author'],
                'avatar' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['music']['cover_large']['url_list'][0],
                'url' => $videoInfo['loaderData']['video_(id)/page']['videoInfoRes']['item_list'][0]['video']['play_addr']['uri']
            )
        )
    );
    return $arr;
}

function extractId($url)
{
    $headers = get_headers($url, true);
    if ($headers === false) {
        // 如果获取头信息失败，直接使用原始 URL
        $loc = $url;
    } else {
        // 处理重定向头可能是数组的情况
        if (isset($headers['Location']) && is_array($headers['Location'])) {
            $loc = end($headers['Location']);
        } else {
            $loc = $headers['Location'] ?? $url;
        }
    }

    // 确保 $loc 是字符串
    if (!is_string($loc)) {
        $loc = strval($loc);
    }

    preg_match('/[0-9]+|(?<=video\/)[0-9]+/', $loc, $id);
    return !empty($id) ? $id[0] : null;
}


function curl($url, $header = null, $data = null)
{
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($con, CURLOPT_AUTOREFERER, 1);
    if (isset($header)) {
        curl_setopt($con, CURLOPT_HTTPHEADER, $header);
    }
    if (isset($data)) {
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($con, CURLOPT_TIMEOUT, 5000);
    $result = curl_exec($con);
    if ($result === false) {
        // 处理 curl 错误
        $error = curl_error($con);
        curl_close($con);
        trigger_error("cURL error: $error", E_USER_WARNING);
        return false;
    }
    curl_close($con);
    return $result;
}

// 使用空合并运算符检查 url 参数
$url = $_GET['url'] ?? '';
if (empty($url)) {
    echo json_encode(['code' => 201, 'msg' => 'url为空'], 480);
} else {
    $response = douyin($url);
    if (empty($response)) {
        echo json_encode(['code' => 404, 'msg' => '获取失败'], 480);
    } else {
        echo json_encode($response, 480);
    }
}
?>
