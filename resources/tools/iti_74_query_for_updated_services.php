<?php
$openinfoman = array(
    'url'=>'http://masala:8984/CSD/csr/CSD-Providers-Connectathon-20150120/careServicesRequest/urn:openhie.org:openinfoman-rapidpro:get_json_for_import/adapter/rapidpro/get'
    );

$rapidpro= array(
    'url' =>'https://app.rapidpro.io/api/v2/contacts.json',
    'base_url' =>'https://app.rapidpro.io/',
    'slug' =>'',
    'auth_token' => '',
    'group_name' => ''
    );



if ($tok = getenv('RAPIDPRO_AUTH_TOKEN')) {
    $rapidpro['auth_token'] = $tok;
}

if ($url = getenv('RAPIDPRO_URL')) {
    $rapidpro['url'] = $url;
}

if ($slug = getenv('RAPIDPRO_SLUG')) {
    $rapidpro['slug'] = $slug;
}

if ($group_name = getenv('RAPIDPRO_GROUP_NAME')) {
    $rapidpro['group_name'] = $group_name;
}

if ($rapidpro["group_name"]) {
$group_uuid=get_group_uuid($rapidpro);
$rapidpro["url"]=$rapidpro["url"]."?group_uuids=".$group_uuid;
}

if ( ! is_array($contacts = get_contacts($rapidpro))
    ){
    if (array_key_exists('HTTP_HOST',$_SERVER)) {
        header('HTTP/1.1 401 Unauthorized', true, 401);
    }
    die("Could not do it. Sorry.");
}


if (array_key_exists('HTTP_HOST',$_SERVER)) {
    header('HTTP/1.1 200 OK', true, 200);
}
echo '<?xml version="1.0" encoding="UTF-8"?>
<CSD xmlns="urn:ihe:iti:csd:2013" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:ihe:iti:csd:2013 CSD.xsd">
  <organizationDirectory/>
  <serviceDirectory/>
  <facilityDirectory/>
  <providerDirectory>
';
flush();
foreach ($contacts as $globalid => $records) {
    if (! ($entry = generate_entry($rapidpro,$globalid,$records))) {
        continue;
    }
    echo $entry;
    flush();
}
echo '  </providerDirectory>
</CSD>';
flush();
die();



function generate_entry($rapidpro,$globalid,$records) {
    $out = false;
    $names = array();
    $uuids = array();
    $group_uuids = array();
    $tels = array();
    foreach ($records as $record) {
        if (array_key_exists('name',$record)
            && is_scalar($name = $record['name'])
            && $name
            ) {
            $names[] = $name;
        }
        if (array_key_exists('uuid',$record)
            && is_scalar($uuid = $record['uuid'])
            && $uuid
            ) {
            $uuids[] = $uuids;
        }
        if (array_key_exists('group_uuids',$record)
            && is_array($g_uuids = $record['group_uuids'])
            ) {
            $group_uuids = array_merge($group_uuids,$g_uuids);
        }
        if ( array_key_exists('urns',$record)
             && is_array($urns = $record['urns'])
             &&  is_array($ts = preg_grep('/^tel\:/',$urns))
            ) {
            foreach ($ts as $i=>$t) {
                if (! ($t = trim(substr($t,4)))) {
                    continue;
                }
                $tels[] = $t;
            }
        }

    }
    if (count($group_uuids) == 0) {
        $group_uuids[] = 'NO_ROLE';//need something for the type
    }
    $group_uuids = array_unique($group_uuids);
    $tels = array_unique($tels);
    $names = array_unique($names);
    if (count($names)  == 0) {
        return $out;
    }
    $out = '    <provider entityID="' . strtolower($globalid) . '">
';
    $out .= "      <otherID code='rapidpro_contact_id' assigningAuthorityName='{$rapidpro['base_url']}{$rapidpro['slug']}'>$uuid</otherID>
";
    foreach ($group_uuids as $group_uuid) {
        $out .= '      <codedType code="' . $group_uuid . '" codingScheme="' . $rapidpro['base_url'] . '"/>
';
    }

    $out .= '      <demographic>
        <name>
';
    foreach ($names as $name) {
        $out .= '          <commonName>'. $name . '</commonName>
';
    }
    $out .= '        </name>
';
    foreach ($tels as $tel) {
        $out .= '        <contactPoint><codedType code="BP" codingScheme="urn:ihe:iti:csd:2013:contactPoint">' . $tel .'</codedType></contactPoint>
';
    }
    $out .= '      </demographic>
';

    $out .= '    </provider>
';
    return $out;
}




function get_contacts($rapidpro) {
    $url = $rapidpro['url'];
    $opts = array(
        'http'=>array(
            'method'=>"GET",
            'header'=>'Authorization: Token ' . $rapidpro['auth_token']
            )
        );
    $context = stream_context_create($opts);
    $contacts = array();
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
            if (!array_key_exists($globalid,$contacts)) {
                $contacts[$globalid] = array();
            }
            $contacts[$globalid][] = $result;
        }
        if (array_key_exists('next',$contacts_json)) {
            $url = $contacts_json['next'];
        } else {
            $url = false;
        }
    }
    return $contacts;
}

function get_group_uuid($rapidpro) {
        $url = $rapidpro["base_url"]."api/v1/groups.json?name=".$rapidpro["group_name"];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HTTPHEADER, Array(
                                                   "Content-Type: application/json",
                                                   "Authorization: Token ".$rapidpro["auth_token"],
                                                  ));
        $output = curl_exec ($ch);
        $output=json_decode($output,true);
        curl_close ($ch);
        return $output["results"][0]["uuid"];
        }
