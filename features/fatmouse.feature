Feature: Fatmouse PHP client

    Scenario: Call task and get result (in-process)

        Given I connect to fatmouse
         When I call task 
         Then I can get result in same process
          And I can acknowledge result


    Scenario: Call task and get result (other process)
        Given I connect to fatmouse
         When I call task
         Then I can get result in other process
          And I can acknowledge result

    # Scenario: Consume events

    #     Given I created fatmouse client using url "amqp://0.0.0.0:5672/"
    #      When I start listening for events from fam server
    #       And I send random event to event queue
    #      Then I see that this very event was handled

    #      When I restart event listener
    #      Then I see that this very event was handled

    #      When I acknowledge event
    #       And I restart event listener
    #      Then I see no new events were sent
