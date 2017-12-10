import module namespace csd_bl = "https://github.com/openhie/openinfoman/csd_bl";
declare default element  namespace   "urn:ihe:iti:csd:2013";
declare variable $careServicesRequest as item() external;

let $county_uuid := string($careServicesRequest/organization/@entityID)
let $data := /CSD/providerDirectory/provider
    [
      (
        starts-with(lower-case(string(./extension/position/@title)),"cso") or
        starts-with(lower-case(string(./extension/position/@title)),"county surveillance")
      )
      and ./facilities/facility/@entityID = /CSD/facilityDirectory/facility[./organizations/organization[@entityID = /CSD/organizationDirectory/organization[./parent/@entityID = $county_uuid]/@entityID ]]/@entityID
    ]
    return <CSD xmlns:csd="urn:ihe:iti:csd:2013"  >
            <organizationDirectory/>
            <serviceDirectory/>
            <facilityDirectory/>
            <providerDirectory>
              {$data}
            </providerDirectory>
           </CSD>
