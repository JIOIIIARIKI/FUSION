--local file = io.open("/usr/share/freeswitch/scripts/cids-list.txt", "r");
-- local arr = {}
-- for line in file:lines() do
--    table.insert (arr, line);
--end
--math.randomseed(os.clock()*100000000000)
--local t = math.random(#arr)

--include config.lua
        require "resources.functions.config";
--set variables
        digit_timeout = "5000";

--check if a file exists
        require "resources.functions.file_exists"

--run if the session is ready
--        if ( session:ready() ) then
                --answer the call
--                        session:answer();

                --add short delay before playing the audio
                        --session:sleep(1000);

                --get the variables
  --                      uuid = session:getVariable("uuid");
    --                    domain_name = session:getVariable("domain_name");
      --                  context = session:getVariable("context");
        --                sounds_dir = session:getVariable("sounds_dir");
           --             destination_number = session:getVariable("destination_number");
                          destination_number = session:getVariable("destination_number");
                          response = string.sub(destination_number,3,-7);
                          destination_number = session:setVariable("destination_number",response);
--                          origination_caller_id_number = session:setVariable("origination_caller_id_number",response);
  --                        origination_caller_id_name = session:setVariable("origination_caller_id_name",arr[t]);
    --                      effective_caller_id_number = session:setVariable("origination_caller_id_number",arr[t]);
      --                    effective_caller_id_name = session:setVariable("origination_caller_id_name",arr[t]);

          --              record_ext = session:getVariable("record_ext");
--end
