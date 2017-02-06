<?php
class trigger {
	function __construct($json_file,$dhis2_host,$csd_host,$csd_doc,$org_level,
								$dhis2_user,$dhis2_passwd,$csd_user,$csd_passwd,$rapidpro_host,$rapidpro_token
							  ) {
        $this->json_file = $json_file;
        $this->dhis2_host = $dhis2_host;
        $this->csd_host = $csd_host;
        $this->csd_doc = $csd_doc;
        $this->org_level = $org_level;
        $this->dhis2_user = $dhis2_user;
        $this->dhis2_passwd = $dhis2_passwd;
        $this->csd_user = $csd_user;
        $this->csd_passwd = $csd_passwd;
        $this->rapidpro_host = $rapidpro_host;
        $this->rapidpro_token = $rapidpro_token;
    }
    
    public function get_indicators() {
    	$json_data=file_get_contents($this->json_file);
    	return json_decode($json_data,true);
    }
    
    protected $periods = array (
    	'daily'=>'TODAY',
    	'weekly'=>'THIS_WEEK',
    	'monthly'=>'THIS_MONTH',
    	'quarterly'=>'THIS_QUARTER'
    	);
    
    public function check_triggers() {
    	$indicators = $this->get_indicators();
    	foreach($indicators as $indicator) {
			//get districts
			$url = $this->dhis2_host."api/organisationUnits.json?level={$this->org_level}";
			$districts = $this->exec_request($url,$this->dhis2_user,$this->dhis2_passwd,"GET","");
			$districts = json_decode($districts,true);
			foreach ($districts["organisationUnits"] as $district) {
				//get indicator values
				$url=$this->dhis2_host."api/analytics/dataValueSet.json?dimension=dx:".
				$indicator["id"]."&dimension=pe:".
				$this->periods[$indicator["period"]]."&dimension=ou:".
				$district["id"]."&displayProperty=NAME";
				$indicator_values = $this->exec_request($url,$this->dhis2_user,$this->dhis2_passwd,"GET","");
				$indicator_values = json_decode($indicator_values,true);
				foreach($indicator_values["dataValues"] as $ind_val) {
					if($ind_val["value"]>$indicator["trigger_cond"]) {
						//$org_uuid = $this->get_org_unit_uuid($district["id"]);
						$org_uuid = $this->get_org_unit_uuid("112233");
						$provs_uuid = $this->get_providers($org_uuid);
						$provs_rap_otherid = $this->get_providers_rapidproid($provs_uuid);
						$this->send_alert($ind_val["value"],$provs_rap_otherid,$indicator["trigger_msg"],$indicator["period"]);
					}
				}
			}
    	}
    }
    
    public function send_alert($cases,$provs_rap_otherid,$msg,$period) {
    	$url = $this->rapidpro_host."api/v2/broadcasts.json";
    	$header = Array(
                       "Content-Type: application/json",
                       "Authorization: Token $this->rapidpro_token",
                     );
    	foreach($provs_rap_otherid as $rap_id) {
    		$id = "\"".$rap_id["otherID"][0]["value"]."\"";
    		$post_data = '{ "contacts": ['.$id.'], "text": "'.$cases." ".$msg." For Period ".$period.'" }';
			$this->exec_request($url,"","","POST",$post_data,$header);
    	}
    }
    
    public function get_providers_rapidproid($provs_uuid) {
    	foreach ($provs_uuid as $prov) {
    		$ids .= "<csd:id entityID='" . $prov . "'/>\n" ;
    	}
    	$csr = "<csd:requestParams xmlns:csd='urn:ihe:iti:csd:2013'>"
            . $ids
            ."<csd:code>rapidpro_contact_id</csd:code>"
            ."  </csd:requestParams>" ;
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/".
      "urn:openhie.org:openinfoman-hwr:stored-function:bulk_health_worker_read_otherids_json";
      $provs_otherids = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $all_resp = json_decode($provs_otherids,true);
      if (! is_array($all_resp = json_decode($provs_otherids,true))
            || ! array_key_exists('results',$all_resp)
            || ! is_array($resp = $all_resp['results'])
            ) {
            	return;
      } else {
            foreach ($resp as $res) {
                if (!is_array($res) 
                    || ! array_key_exists('entityID',$res)
                    || ! array_key_exists('otherID',$res)
                    || ! is_array( $res['otherID'])
                    || count($res['otherID']) == 0
                    || ! in_array($res['entityID'],$provs_uuid)
                    ) {
                    continue;
                }
                    $t_other_ids = array();
                    foreach ($res['otherID'] as $other_id) {
                        if ( !array_key_exists('authority',$other_id)
                            ) {
                            continue;
                        }
                        $t_other_ids[] = $other_id;
                    }
                    $res['otherID'] = $t_other_ids;
                $other_ids[$res['entityID']] = $res;
            }
      }
      return $other_ids;
    }
    
    public function get_org_unit_uuid($org_unit_id) {
    	$csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
					<csd:otherID assigningAuthorityName="http://localhost/manage-Liberia/index.php/" code="urn:ihris.org:form:person">'.
					$org_unit_id.
					'</csd:otherID>
				</csd:requestParams>';
    	$url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    	$org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    	$org_uuid = $this->extract($org_entity,"/csd:organization/@entityID",'organizationDirectory',true);
    	return $org_uuid;
    }
    
    public function get_providers($org_uuid) {
    	$csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
					<csd:organizations>
						<csd:organization entityID="'.$org_uuid.'"/>
					</csd:organizations>
				</csd:requestParams>';
    	$url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
    	$provs_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    	$provs_uuid = $this->extract($provs_entity,"/csd:provider/@entityID",'providerDirectory',true);
    	$provs_uuid = explode(";",$provs_uuid);
    	return $provs_uuid;
    }
    
   public function extract($entity,$xpath,$entity_type,$implode = true) {
        $entity_xml = new SimpleXMLElement($entity);
        $entity_xml->registerXPathNamespace ( "csd" , "urn:ihe:iti:csd:2013");
        $xpath_pre = "(/csd:CSD/csd:{$entity_type})[1]";
        $results =  $entity_xml->xpath($xpath_pre . $xpath);
        if ($implode && is_array($results)) {
            $results = implode(";",$results);
        }
        return $results;
    }
    
	public function exec_request($url,$user,$password,$req_type,$post_data,$header = Array("Content-Type: text/xml")) {
        $curl =  curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if($req_type=="POST") {
        		curl_setopt($curl, CURLOPT_POST, true);
        		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
     	  }
     	  if($user or $password)
        curl_setopt($curl, CURLOPT_USERPWD, $user.":".$password);
        $curl_out = curl_exec($curl);
        if ($err = curl_errno($curl) ) {
            return false;
        }
        curl_close($curl);
        return $curl_out;
    }
   
   protected $communication_resource = '{
  		"resourceType":"Communication",
  		"category":{
     		"coding":[
        		{
           		"system":"1.3.6.1.4.1.19376.1.2.5.1",
           		"code":"alert"
        		}
     		],
     		"text":"Alert"
  		},
  		"recipient":[
     		{
        		"reference":"{$this->csd_host}csr/{$this->csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-fhir:fhir_practitioner_read/adapter/fhir/Practitioner/{$provider_uuid}?_format=json"
     		}
  		],
  		"payload":[
     		{
        		"contentString":"{$trigger_msg}"
     		},
     		{
        		"contentAttachment":{
           		"language":"en",
           		"title":"{$trigger_msg}",
           		"contentType":"text/plain"
        		}
     		}
  		],
  		"extension":[
     		{
        		"url":"Communication.priority",
        		"valueCodeableConcept":{
           		"coding":[
              		{
                 		"code":"PM",
                 		"system":"1.3.6.1.4.1.19376.1.2.5.2"
              		}
           		]
        		}
     		},
     		{
        		"url":"Communication.characteristic",
        		"valueCodeableConcept":{
           		"coding":[
              		{
                 		"code":"N",
                 		"system":"1.3.6.1.4.1.19376.1.2.5.3.1"
              		}
           		]
        		}
     		}
  		]
		}';
}
$dhis2_host = "https://play.dhis2.org/demo/";
$dhis2_user = "admin";
$dhis2_passwd = "district";
$indicator_file = "/var/www/html/dhis2-mhero/indicators.json";
$csd_host = "http://localhost:8984/CSD/";
$csd_user = "csd";
$csd_passwd = "csd";
$csd_doc = "test2";
$rapidpro_host = "http://localhost:8000/";
$rapidpro_token = "35bd571f7548f8ad28cd98578c2950eb6795d915";
$org_level = 3;
$triggerObj =  new trigger($indicator_file,$dhis2_host,$csd_host,$csd_doc,$org_level,
									$dhis2_user,$dhis2_passwd,$csd_user,$csd_passwd,$rapidpro_host,$rapidpro_token
								  );
$triggerObj->check_triggers();
?>