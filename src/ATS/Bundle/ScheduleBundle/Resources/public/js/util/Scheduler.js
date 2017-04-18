/**
 * The utility wrapper that works with FullCalendar.
 *
 * @author Austin Shinpaugh
 */

const Scheduler = (function ($) {
    "use strict";
    
    let Scheduler = function (calendar)
    {
        if (!calendar) {
            throw new Error('Missing calendar parameter.');
        }
        
        this.calendar = $(calendar);
    };
    
    Scheduler.prototype = {
        /**
         * Initialize the calendar.
         * 
         * @var object options The options to override the default settings.
         * 
         * @return Scheduler
         */
        init : function (options)
        {
            let defaults = {
                allDaySlot:  false,
                defaultView: 'agendaWeek',
                weekends:    false,
                defaultDate: moment(),
                minTime:     "08:00:00",
                header: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'agendaWeek,agendaDay'
                },
                viewRender: function (view) {
                    updateHeader();
                    hideDateColumnHeader();
                },
                eventRender: function (event, element) {
                    if (!event.hasOwnProperty('description')) {
                        return;
                    }
                    
                    element.find('.fc-title')
                        .append("<br/>" + event.description)
                    ; 
                },
                dayClick: function(date, jsEvent, view) {
                    console.log(date);
                    console.log(jsEvent);
                    console.log(view);
                }
            };
            
            this.calendar.fullCalendar(Object.assign(defaults, options));
            
            return this;
        },
        
        /**
         * Request the classes based on the applied filters.
         */
        fetch : function (uri) {
            let context = this;
            
            if (!uri) {
                uri = this.buildUri();
            }
            
            this.calendar.fullCalendar('addEventSource', {
                url:   GlobalUtils.getAPIUrl(uri),
                type:  'GET',
                data:  getData(),
                cache: true,
                
                eventDataTransform: function (data) {
                    console.log('data transform');
                    console.log(data);
                },
                complete : function (data) {
                    context.loadCourseClass(data.responseJSON);
                },
                error: function (xhr) {
                    console.log('error:');
                    console.log(xhr);
                }
            });
            
            updateHeader();
        },
        
        buildUri : function () {
            let uri, data;
            uri  = '/class';
            //data = getData();
            
            uri = uri
                //+ (data.block   ? '/' + data.block   : '')
                //+ (data.subject ? '/' + data.subject : '')
                + '.json'
            ;
            
            return uri;
        },
        
        getData : function () {
            return {
                'instructor': $('#instructor').val()
            };
        },
        
        loadCourseClass : function (data) {
            let events;
            
            if (data.hasOwnProperty('classes')) {
                events = loadEventAsClass(data.classes);
            } else {
                events = getClasses(data.courses);
            }
            
            this.calendar.fullCalendar('addEventSource', {
                'events': filterEvents(this.calendar, events)
            });
            
        },

        /**
         * Clear the calendar.
         */
        clear : function () {
            this.calendar.fullCalendar('removeEventSources');
        }
    };

    /**
     * Hides the month / day in the column week headers.
     */
    function hideDateColumnHeader ()
    {
        $('.fc-day-header span').each(function () {
            let text, parts;
            text  = $(this).text();
            parts = text.split(' ');
            
            $(this).text(parts[0]);
        });
    }

    /**
     * Update the header based on the semester.
     */
    function updateHeader()
    {
        let title = $('#term option:selected').text();
        
        $('#calendar')
            .find('.fc-header-toolbar h2')
            .html(title ? title : 'No Semester')
        ;
    }

    /**
     * Make sure that there are no duplicate events rendered in the calendar.
     * 
     * @param {fullCalendar} calendar
     * @param {Event[]}       events
     * @returns {Array}
     */
    function filterEvents(calendar, events)
    {
        let output, idx;
        
        output = [];
        for (idx in events) {
            let event = events[idx];
            
            if (calendar.fullCalendar('clientEvents', event.id).length) {
                continue;
            }
            
            output.push(event);
        }
        
        return output;
    }

    /**
     * Parse the JSON API data and turn them into event objects.
     * 
     * @param classes
     * @returns {Array}
     */
    function loadEventAsClass(classes)
    {
        let events, i;
        events = [];
        
        for (i in classes) {
            let cls, course, days, subject;
            cls     = classes[i];
            course  = cls.course;
            days    = cls.days;
            subject = cls.subject;
            
            if (days && !days.length) {
                continue;
            }
            
            events.push({
                id:    cls.crn,
                title: subject.name + ' ' + course.number,
                start: getTime(cls.start_time),
                end:   getTime(cls.end_time),
                dow:   getDays(cls.days),
                description: cls.instructor.name
                
            });
        }
        
        return events;
    }
    
    function getClasses (courses)
    {
        let events, i;
        
        events = [];
        
        for (i in courses) {
            let course, classes, x;
            course  = courses[i];
            classes = course.classes;
            
            for (x in classes) {
                let cls, days;
                cls  = classes[x];
                days = getDays(cls.days);
                
                if (!days.length) {
                    continue;
                }
                
                events.push({
                    id:    cls.crn,
                    title: course.subject + ' ' + course.number,
                    start: getTime(cls.start_time),
                    end:   getTime(cls.end_time),
                    dow:   getDays(cls.days)
                });
            }
        }
        
        return events;
    }

    /**
     * Turn the stored dates into their numerical representation.
     * 
     * @param {string} strDays
     * @returns {Array}
     */
    function getDays (strDays)
    {
        if (!strDays.length) {
            return [];
        }
        
        let dow, days, parts, idx;
        dow   = ['Sun', 'M', 'T', 'W', 'R', 'F', 'Sat'];
        days  = [];
        parts = strDays.split('/');
        
        for (idx in parts) {
            let initial = parts[idx];
            days.push(dow.indexOf(initial));
        }
        
        return days;
    }
    
    function getTime (strTime)
    {
        let time = 4 === strTime.length ? strTime : '0' + strTime;
        
        return time.substr(0, 2) + ':' + time.substr(2);
    }

    /**
     * Get the data used in the query to the API Endpoint.
     * 
     * @returns {{term: *, block: *, subject: *, instructor: *}}
     */
    function getData()
    {
        return {
            'term'      : $('#term').val(),
            'block'     : $('#term-block').val(),
            'subject'   : $('#subject').val(),
            'instructor': $('#instructor').val()
        };
    }
    
    return Scheduler;
}) (jQuery);
