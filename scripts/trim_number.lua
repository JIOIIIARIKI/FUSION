require "resources.functions.config";

        digit_timeout = "5000";

        require "resources.functions.file_exists"

                          destination_number = session:getVariable("destination_number");
                          response = string.sub(destination_number,0,-7);
                          destination_number = session:setVariable("destination_number",response);
