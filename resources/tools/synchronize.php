<?php
$openinfoman = array(
    'url'=>'http://localhost:8984/CSD/csr/mhero_liberia_ihris/careServicesRequest/urn:openhie.org:openinfoman-rapidpro:get_json_for_import/adapter/rapidpro/get',
    'username'=>'oim',
    'password'=>'oim'
    );

$rapidpro= array(
    'url' =>'https://app.rapidpro.io/api/v2/contacts.json',
    'auth_token' => '',
    'group_name' => '',
    );


if ($url = getenv('OPENINFOMAN_URL')) {
    $openinfoman['url'] = $url;
}

if ($tok = getenv('RAPIDPRO_AUTH_TOKEN')) {
    $rapidpro['auth_token'] = $tok;
}

if ($url = getenv('RAPIDPRO_URL')) {
    $rapidpro['url'] = $url;
}

if ($group_name = getenv('RAPIDPRO_GROUP_NAME')) {
    $rapidpro['group_name'] = $group_name;
}

$context = stream_context_create(array(
    'http' => array(
        'header'  => "Authorization: Basic " . base64_encode($openinfoman['username'].":".$openinfoman['password'])
    )
));
if (! ($contacts_text = file_get_contents($openinfoman['url'],false,$context))
    || ! is_array($contacts_json_full = json_decode($contacts_text,true))
    || ! array_key_exists('contacts',$contacts_json_full)
    || ! is_array($contacts_json = $contacts_json_full['contacts'])
    || ! is_array($current = get_current($rapidpro))
    ){
    if (array_key_exists('HTTP_HOST',$_SERVER)) {
        header('HTTP/1.1 401 Unauthorized', true, 401);
    }
    die("Could not do it. Sorry.");
}
$records = generate_records($contacts_json,$current,$rapidpro);


foreach ($records as $record) {
    if(array_key_exists("group_uuids",$record))
    unset($record["groups"]);    
    $data_string = json_encode($record,JSON_NUMERIC_CHECK);
$data = json_decode($data_string,true);
foreach($data["urns"] as $id=>$urn) {
$urn = trim($urn);
$pos = strpos($urn,"+231");
if($pos === false){
        if(strpos($urn,"tel:0") !== false)
        $urn = str_replace("tel:0","tel:+231",$urn);
        else if(strpos($urn,"tel:8") !== false)
        $urn = str_replace("tel:8","tel:+2318",$urn);
        else if(strpos($urn,"tel:7") !== false)
        $urn = str_replace("tel:7","tel:+2317",$urn);
        else {
        echo "Wrong====> ".$urn;
        continue;
        }
}
$data["urns"][$id] = $urn;
}
$data_string = json_encode($data,JSON_NUMERIC_CHECK);
    $ch = curl_init($rapidpro['url'] );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Authorization: Token ' . $rapidpro['auth_token'],
                    'Content-Length: ' . strlen($data_string))
        );
    
    $result = curl_exec($ch);
    $result = json_decode($result,true);
    print_r($record);
    echo $data_string;
    var_dump($result);
    if (curl_errno($ch) != 0) {
        var_dump( curl_error($ch));
        var_dump(curl_getinfo($ch));
    }
    curl_close($ch);
sleep(2);
}

if (array_key_exists('HTTP_HOST',$_SERVER)) {
    header('HTTP/1.1 200 OK', true, 200);
}
die("Could do it.");


function generate_records($contacts_json,$current,$rapidpro) {
    $records = array();
    echo "Generating records for "  . count($contacts_json) . "/" . count($current)  . "\n";
    foreach ($contacts_json as $contact) {
        if (!is_array($contact)
            || !array_key_exists('fields',$contact)
            || ! is_array($fields = $contact['fields'])
            || ! array_key_exists('globalid',$fields)
            || ! is_scalar($globalid = $fields['globalid'])
            || ! $globalid
            || ! array_key_exists('urns',$contact)
            || ! is_array($urns = $contact['urns'])
            || ! is_array($tels = preg_grep('/^tel\:/',$urns))
            || count($tels) == 0
            ){
            continue;
        }
        if (array_key_exists($globalid,$current)) {
            $record = $current[$globalid];
            if (!array_key_exists('urns',$record)
                || ! is_array($record['urns'])) {
                $record['urns'] = array();
            }
            $urns = array_unique($urns); 
            foreach ($urns as $urn) {
                if (in_array($urn,$record['urns'])){
                    continue;
                }
$pos = strpos($urn,"+231");
if($pos === false){
	if(strpos($urn,"tel:0") !== false)
	$urn = str_replace("tel:0","tel:+231",$urn);
	else if(strpos($urn,"tel:8") !== false)
	$urn = str_replace("tel:8","tel:+2318",$urn);
	else if(strpos($urn,"tel:7") !== false)
        $urn = str_replace("tel:7","tel:+2317",$urn);
	else {
	echo "Wrong====> ".$urn;
	continue;
	}
}
                $record['urns'][] = $urn;
            }
            if (array_key_exists('phone',$record)) {
                $record['urns'] = array_unique(array_merge(array('tel:' . $record['phone']), $record['urns']));
                unset($record['phone']);            
            }
        } else {
            $record = array(
                'urns'=>$urns,
                'fields'=>array('globalid'=>$globalid)
                );

        }
        if (array_key_exists('name',$contact)){
            $record['name']= $contact['name'];
        }
        //unset($record['uuid']);
	if($rapidpro["group_name"])
	$record['groups'] = array($rapidpro["group_name"]);
        $record['urns'] = array_values($record['urns']);
        foreach ($record['fields'] as $k=>$v) {
            if (!is_string($v)
                && strlen($v) == 0
                ) {
                unset($record['fields'][$k]);
            }
                
        }
        $records[]  = $record;
    }
    return $records;
}

function get_current($rapidpro) {
    $url = $rapidpro['url'];
    $opts = array(
        'http'=>array(
            'method'=>"GET",
            'header'=>'Authorization: Token ' . $rapidpro['auth_token']
            )
        );
    $context = stream_context_create($opts);
    $current = array();
    while ($url
           && ($contacts_text = file_get_contents($url,false,$context))
           && is_array($contacts_json =json_decode($contacts_text,true))
           && array_key_exists('results',$contacts_json)
           && is_array($results = $contacts_json['results'])
        ) {
        foreach ($results as $result) {
            if (!array_key_exists('fields',$result)
                || !is_array($fields = $result['fields'])
                || ! array_key_exists('globalid',$fields)
                || ! is_scalar($globalid = $fields['globalid'])
                || ! $globalid
                ) {
                continue;
            }
            $current[$globalid] = $result;
        }
        if (array_key_exists('next',$results)) {
            $url = $results['next'];
        } else {
            $url = false;
        }
    }
    return $current;
}


