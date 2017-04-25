/**
 * Global utility used in other scripts.
 * 
 * @author Austin Shinpaugh
 */

let GlobalUtils;
{
    let semesters   = [];
    let instructors = [];
    let subjects    = [];
    
    GlobalUtils = function GlobalUtils()
    {
        
    };

    /**
     * Get a URI that can be used in either dev or prod.
     * 
     * @param {string} path
     * 
     * @returns {string}
     */
    GlobalUtils.getAPIUrl = function (path)
    {
        return (GlobalUtils.isDev() ? '/app_dev.php' : '') + path;
    };

    /**
     * Determine if we're in the dev environment.
     * 
     * @return {boolean}
     */
    GlobalUtils.isDev = function ()
    {
        return location.pathname.indexOf('app_dev.php') > -1;
    };

    /**
     * Return the available semesters
     * 
     * @returns {object}
     */
    GlobalUtils.getSemesters = function ()
    {
        return semesters;
    };

    /**
     * Sets the available semesters.
     * 
     * @param data
     */
    GlobalUtils.setSemesters = function (data)
    {
        semesters = data.terms;
    };

    /**
     * Sets the JSON feed of instructors.
     * 
     * @param data
     */
    GlobalUtils.setInstructors = function (data)
    {
        if (data.hasOwnProperty('instructors')) {
            instructors = data.instructors;
        } else {
            instructors = data;
        }
    };

    /**
     * Get the Instructors.
     * 
     * @return Object
     */
    GlobalUtils.getInstructors = function ()
    {
        return instructors;
    };
    
    /**
     * Sets the JSON feed of subjects.
     * 
     * @param data
     */
    GlobalUtils.setSubjects = function (data)
    {
        if (data.hasOwnProperty('subjects')) {
            subjects = data.subjects;
        } else {
            subjects = data;
        }
    };

    /**
     * Get the subjects.
     * 
     * @return Object
     */
    GlobalUtils.getSubjects = function ()
    {
        return subjects;
    };
    
    /**
     * Returns the subject from the URL.
     * @returns {string}
     */
    GlobalUtils.getUriSubject = function ()
    {
        let path, parts, subject;
        path  = window.location.pathname;
        parts = path.split('/');
        
        subject = parts[parts.length - 1];
        
        return -1 === subject.indexOf('.') ? subject : subject.substr(0, subject.indexOf('.'));
    };
}
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
                    left: 'prev,next',
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
                }
            };
            
            this.calendar.fullCalendar(Object.assign(defaults, options));
            
            return this;
        },
        
        /**
         * Request the classes based on the applied filters.
         */
        fetch : function (uri) {
            if (!requiredFields()) {
                return false;
            }
            
            let context = this;
            
            if (!uri) {
                uri = this.buildUri();
            }
            
            this.wipe();
            this.calendar.fullCalendar('addEventSource', {
                url:   GlobalUtils.getAPIUrl(uri),
                type:  'GET',
                data:  context.getData(),
                cache: true,
                
                complete : function (data) {
                    context.loadCourseClass(data.responseJSON);
                },
                error: function (xhr) {
                    console.log('error:');
                    console.log(xhr);
                }
            });
            
            updateHeader();
            
            return true;
        },

        /**
         * Goofy little wrapper around URI building; future-proofing and whatnot.
         * @returns {string}
         */
        buildUri : function () {
            return '/class.json';
        },

        /**
         * Gets the raw data from the input controls and filters out
         * any unnecessary data so that the query string isn't filled
         * with empty data.
         * 
         * @returns {{term: *, block: *, subject: *, instructor: *}|*}
         */
        getData : function () {
            let data, idx;
            data = getData();
            
            for (idx in data) {
                if (!data.hasOwnProperty(idx)) {
                    continue;
                }
                
                let value = data[idx];
                
                if (!value || (value instanceof Array && !value.length)) {
                    delete data[idx];
                }
            }
            
            return data;
        },

        /**
         * Handles the data returned from a graceful API response.
         * 
         * @param data
         */
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
            
            return this;
        },

        /**
         * Clear the applied filters.
         */
        clearFilters : function () {
            let selectors, options;
            selectors = $('.chosen-select');
            options   = selectors.find('option[value]:selected');
            
            // $.removeAttr is broken for the selected property.
            options.prop('selected', false);
            
            // Hide related fields (term-blocks, course numbers).
            selectors.trigger('change');
            selectors.trigger('chosen:updated');
            
            if (!this.calendar.fullCalendar('clientEvents').length) {
                updateHeader();
            }
            
            return this;
        },

        /**
         * Clear all calendar data.
         */
        clear : function () {
            this.wipe().clearFilters();
            
            return this;
        },

        /**
         * Wipe the calendar data.
         */
        wipe : function () {
            this.calendar.fullCalendar('removeEventSources');
            
            return this;
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
        let events, color, border, i;
        events = [];
        color  = '#001505';
        border = '#992600';
        
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
                description:     cls.instructor.name,
                borderColor:     border,
                backgroundColor: color
                
            });
        }
        
        return events;
    }

    /**
     * Ensures the required fields have appropriate values before submitting
     * an API request.
     * 
     * @returns {boolean}
     */
    function requiredFields()
    {
        let term, block, multiples, idx;
        term      = $('#term');
        block     = $('#term-block');
        multiples = ['#subject', '#instructor'];
        
        toggleOrangeBorder(false);
        
        if (!term.val()) {
            term.trigger('chosen:activate');
            return false;
        }
        
        if (!block.val().length) {
            block.trigger('chosen:open');
            return false;
        }
        
        for (idx in multiples) {
            if (!multiples.hasOwnProperty(idx)) {
                continue;
            }
            
            let selector = multiples[idx];
            if ($(selector).val().length) {
                return true;
            }
        }
        
        toggleOrangeBorder(true);
        
        return false;
    }

    /**
     * Highlights the required fields, either subject(s) or instructor(s).
     * 
     * @param {Boolean} on
     */
    function toggleOrangeBorder(on)
    {
        let multiples, color, idx;
        multiples = ['#subject', '#instructor'];
        color     = on ? 'orange' : '';
        
        for (idx in multiples) {
            if (!multiples.hasOwnProperty(idx)) {
                continue;
            }
            
            let selector = multiples[idx];
            $(selector + '_chosen')
                .find('ul')
                .css('border-color', color)
            ;
        }
    }

    /**
     * Used when the API data returned uses the course controller.
     * {@deprecated}
     * 
     * @param courses
     * @returns {Array}
     */
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
            'block'     : filterMultiSelects($('#term-block')),
            'subject'   : filterMultiSelects($('#subject')),
            'number'    : filterMultiSelects($('#number')),
            'instructor': filterMultiSelects($('#instructor'))
        };
    }

    /**
     * Filters out useless values returned from Chosen/jQuery's val() method.
     * 
     * @param {jQuery} select
     * @returns {Array}
     */
    function filterMultiSelects(select)
    {
        let output, values, idx;
        output = [];
        values = select.val();
        
        for (idx in values) {
            if (!values.hasOwnProperty(idx)) {
                continue;
            }
            
            let value = values[idx];
            if (!value.length) {
                continue;
            }
            
            output.push(value);
        }
        
        return output;
    }
    
    return Scheduler;
}) (jQuery);
