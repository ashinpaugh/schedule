/**
 * Home page javascript file. Loads the calendar and requests data to
 * populate it with.
 *
 * @author Austin Shinpaugh
 */

(function ($) {
    "use strict";
    
    let scheduler;
    
    if (!$.fullCalendar) {
        console.log('FullCalendar is not loaded.');
        return;
    }
    
    /*
     * Setup the modal filters.
     * 
     * Using window load to ensure that the data in GlobalUtils was parsed.
     */
    $(window).on('load', function () {
        scheduler = new Scheduler('#calendar');
        scheduler.init();
        
        populateFilters();
        buttonActions();
    });

    /**
     * Fill the filter boxes with their respective data.
     */
    function populateFilters()
    {
        fillSelect('#subject', GlobalUtils.getSubjects());
        fillSelect('#instructor', GlobalUtils.getInstructors());
        fillSelect('#term', GlobalUtils.getSemesters());
        
        bindSemesterChange();
        bindSubjectChange();
        
        $('.modal-body').on('keydown', function (e) {
            if (e.keyCode !== 27) {
                return;
            }
            
            let element = $(e.target);
            if (!element.hasClass('chosen-search-input')) {
                return;
            }
            
            element
                .blur()
                .focus()
            ;
            
            e.stopImmediatePropagation();
        });
    }

    /**
     * Fill a select field and set it up with Chosen.
     * 
     * @param {string} id
     * @param {object} data
     */
    function fillSelect(id, data)
    {
        let select, idx;
        select = $(id);
        
        for (idx in data) {
            if (!data.hasOwnProperty(idx)) {
                return;
            }
            
            let item = data[idx];
            $('<option>')
                .attr('value', item.id)
                .text(determineChosenLabel(item))
                .appendTo(select)
            ;
        }
        
        // Chosen will initialize at 0px because it's in a modal.
        select.chosen({ 
            width: '100%',
            allow_single_deselect:  1/*,
            inherit_select_classes: true
            ,
            From a DevOps perspective, soft-limiting this just makes sense. From
            someone who wants to graduate and impress - what are you gonna do?
            max_selected_options:  3,*/
        });
    }

    /**
     * Determines an appropriate option display text based on the information
     * provided from the entity that the user is selecting from.
     * 
     * @param {object} entity
     * 
     * @returns string
     */
    function determineChosenLabel(entity)
    {
        if (entity.hasOwnProperty('display_name')) {
            return entity.display_name;
        }
        
        if (!entity.hasOwnProperty('level')) {
            return entity.name;
        }
        
        return entity.number + ' | ' + entity.name;
    }

    /**
     * Whenever a change in semester selection happens, update the
     * term-block selector.
     */
    function bindSemesterChange()
    {
        $('#term').on('change', function (event, params) {
            // params is undefined when you deselect a semester.
            if (!params) {
                $('#term-block').chosen('destroy');
                return;
            }
            
            let semesters, semester, select, idx;
            semesters = GlobalUtils.getSemesters();
            for (idx in semesters) {
                if (!semesters.hasOwnProperty(idx)) {
                    continue;
                }
                
                semester = semesters[idx];
                if (semester.id != params.selected) {
                    continue;
                }
                
                select = $('#term-block');
                // If there are other options in the term-block selector, remove them.
                select.find('option[value]').remove();
                select.show();
                
                // Fill the term-block selector.
                fillSelect('#term-block', semester.blocks);
                
                // Notify Chosen that the content of the select box changed.
                select.trigger("chosen:updated");
            }
            
        });
    }
    
    /**
     * Whenever a change in subject selection happens, update the
     * course number selector.
     */
    function bindSubjectChange()
    {
        $('#subject').on('change', function (event, params) {
            // params is undefined when you deselect a subject.
            if (!params) {
                $('#number').chosen('destroy');
                return;
            }
            
            let subjects, select, subject, idx;
            subjects = GlobalUtils.getSubjects();
            select   = $('#number');
            
            if (params.hasOwnProperty('deselected')) {
                select
                    .find('option[data-subject="' + params.deselected + '"]')
                    .remove()
                ;
                
                if (!$(this).val().length) {
                    select.chosen('destroy');
                } else {
                    select.trigger('chosen:updated');
                }
                
                return;
            }
            
            for (idx in subjects) {
                if (!subjects.hasOwnProperty(idx)) {
                    continue;
                }
                
                subject = subjects[idx];
                if (subject.id != params.selected) {
                    continue;
                }
                
                fillSelect('#number', subject.courses);
                
                select.find('option:not([data-subject])')
                    .attr('data-subject', subject.id)
                ;
                
                select.trigger('chosen:updated');
            }
        });
    }

    /**
     * Binds the page buttons to the related actions.
     */
    function buttonActions ()
    {
        let modal = $('#filtersModal');
        modal.find('#apply-filters').on('click', function () {
            if (scheduler.fetch()) {
                modal.modal('hide');
            }
        });
        
        modal.find('#clear-filters').on('click', function () {
            scheduler.clearFilters();
        });
        
        $('#clear-calendar').click(function () {
            scheduler.clear();
        });
        
        $('#btn-export').on('click', function () {
            fetchCsvExport();
        });
        
        // Something is removing the disabled attr - add it back.
        GlobalUtils.toggleExportBtn(scheduler);
    }

    /**
     * Builds a URI to fetch a CSV based on the displayed events.
     */
    function fetchCsvExport()
    {
        let uri, ids, i;
        
        uri = '';
        ids = scheduler.getSectionIds();
        
        for (i in ids) {
            if (!ids.hasOwnProperty(i)) {
                continue;
            }
            
            if (uri.length) {
                uri += '&';
            }
            
            uri += 'section[]=' + ids[i];
        }
        
        location.href = GlobalUtils.getAPIUrl('/download/export.json') + '?' + uri;
    }
    
}) (jQuery);