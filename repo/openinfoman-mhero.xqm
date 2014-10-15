module namespace mhero = "https://github.com/openhie/openinfoman-mhero";

import module namespace csd_lsc = "https://github.com/openhie/openinfoman/csd_lsc";
declare namespace csd  =  "urn:ihe:iti:csd:2013";

declare variable $mhero:authority := 'http://rapidpro.io';


declare updating function mhero:merge_into($mhero_doc,$src_doc) {  
   (
    csd_lsc:update_directory($mhero_doc/csd:CSD/csd:organizationDirectory,$src_doc/csd:CSD/csd:organizationDirectory)
    ,csd_lsc:update_directory($mhero_doc/csd:CSD/csd:facilityDirectory,$src_doc/csd:CSD/csd:facilityDirectory)
    ,csd_lsc:update_directory($mhero_doc/csd:CSD/csd:serviceDirectory,$src_doc/csd:CSD/csd:serviceDirectory)
    ,
    for $src_prov in $src_doc/csd:CSD/csd:providerDirectory/csd:provider
    let $src_urn := $src_prov/@urn
    let $mhero_prov := ($mhero_doc/csd:CSD/csd:providerDirectory/csd:provider[@urn = $src_urn])[1]
    return
      if (exists($mhero_prov)) 	
      then (:REPLACE EXCEPT PERSERVE MHERO IDS:)
        let $upd_prov := 
	  (
            copy $t_upd_prov := $src_prov
	    modify(
              for $mheroid in  $mhero_prov/csd:otherID[starts-with(@assigningAuthorityName , $mhero:authority)]
	      let $assName := $mheroid/@assigningAuthorityName
	      let $upd_mheroid := ($t_upd_prov/csd:otherID[@assigningAuthorityName = $assName and @code = $mheroid/@code])[1]	    
	      return
		if (exists($upd_mheroid))
		  then replace node $upd_mheroid with $mheroid (:OR COULD DO NOTHING.  Theoretically there could be other attributes on $mheroid  :)
		else insert node $mheroid into $t_upd_prov
	    )	   
	    return $t_upd_prov
	  )
        return replace node $mhero_prov with $upd_prov	
      else (:INSERT :)
	insert node $src_prov into $mhero_doc/csd:CSD/csd:providerDirectory
    )
};


