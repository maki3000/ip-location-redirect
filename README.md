# Wordpress - IP location redirect

## description
choose countries and redirect depending on their IP address

## testing
- only tested with Wordpress 6.4.2
- only tested with one redirect if is country and another redirect if is NOT country

## needs fix
- I think settings the IDs and the FOR attributes for the repeater action radios does not work on new/delete repeater item 

## TODOs
- make replacing text content placeholder fail safe
- ev. put ajax endpoint process_location_url_change() into class ipLocationRedirect
- add more IP APIs options
- add sort to redirect repeater
- ev. make static ip_location_api_called_limit (now 5) and make it editable in backend (after how many IP API attempts is plugin silent)
- make text (aktuell) editable in backend
