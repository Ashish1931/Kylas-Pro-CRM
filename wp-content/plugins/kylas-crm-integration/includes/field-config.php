<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Default Kylas CRM Lead Fields configuration
 */
function kylas_crm_get_default_fields() {
    return array(
        // Personal Information
        'firstName'          => array('label' => 'First Name', 'type' => 'Text'),
        'lastName'           => array('label' => 'Last Name', 'type' => 'Text', 'required' => true),
        'aboutYou'           => array('label' => 'Tell me about yourSelf', 'type' => 'Text'),
        'salutation'         => array('label' => 'Salutation', 'type' => 'Pick List'),
        'emails'             => array('label' => 'Emails', 'type' => 'Email', 'required' => true),
        'phoneNumbers'       => array('label' => 'Phone Numbers', 'type' => 'Phone'),
        'designation'        => array('label' => 'Designation', 'type' => 'Text'),
        'dnd'                => array('label' => 'Do Not Disturb', 'type' => 'Boolean'),
        
        // Address Information
        'address'            => array('label' => 'Address', 'type' => 'Text'),
        'city'               => array('label' => 'City', 'type' => 'Text'),
        'state'              => array('label' => 'State', 'type' => 'Text'),
        'zipcode'            => array('label' => 'Zipcode', 'type' => 'Text'),
        'country'            => array('label' => 'Country', 'type' => 'Pick List'),
        'addressCoordinate'  => array('label' => 'Address Coordinate', 'type' => 'Text'),
        
        // Company Information
        'companyName'        => array('label' => 'Company Name', 'type' => 'Text'),
        'companyAddress'     => array('label' => 'Company Address', 'type' => 'Text'),
        'companyCity'        => array('label' => 'Company City', 'type' => 'Text'),
        'companyState'       => array('label' => 'Company State', 'type' => 'Text'),
        'companyZipcode'     => array('label' => 'Company Zipcode', 'type' => 'Text'),
        'companyCountry'     => array('label' => 'Company Country', 'type' => 'Pick List'),
        'companyEmployees'   => array('label' => 'Company Employees', 'type' => 'Pick List'),
        'companyAnnualRevenue' => array('label' => 'Company Annual Revenue', 'type' => 'Number'),
        'companyWebsite'     => array('label' => 'Company Website', 'type' => 'URL'),
        'companyPhones'      => array('label' => 'Company Phones', 'type' => 'Phone'),
        'companyIndustry'    => array('label' => 'Company Industry', 'type' => 'Pick List'),
        'companyBusinessType' => array('label' => 'Business Type', 'type' => 'Pick List'),
        'companyAddressCoordinate' => array('label' => 'Company Address Coordinate', 'type' => 'Text'),
        'department'         => array('label' => 'Department', 'type' => 'Text'),
        
        // Social Media
        'facebook'           => array('label' => 'Facebook', 'type' => 'URL'),
        'twitter'            => array('label' => 'Twitter', 'type' => 'URL'),
        'linkedIn'           => array('label' => 'Linked In', 'type' => 'URL'),
        
        // Pipeline & Sales Information
        'pipeline'           => array('label' => 'Pipeline', 'type' => 'Text'),
        'pipelineStage'      => array('label' => 'Pipeline Stage', 'type' => 'Text'),
        'pipelineStageReason' => array('label' => 'Pipeline Stage Reason', 'type' => 'Text'),
        'forecastingType'     => array('label' => 'Forecasting Type', 'type' => 'Text'),
        
        // Requirements & Products
        'requirementName'    => array('label' => 'Requirement', 'type' => 'Text'),
        'requirementCurrency' => array('label' => 'Currency', 'type' => 'Pick List'),
        'requirementBudget'  => array('label' => 'Budget', 'type' => 'Number'),
        'expectedClosureOn'  => array('label' => 'Expected Closure On', 'type' => 'Date'),
        'actualClosureDate'  => array('label' => 'Actual Closure Date', 'type' => 'Date'),
        'products'           => array('label' => 'Products or Services', 'type' => 'Text'),
        
        // Conversion Information
        'convertedAt'        => array('label' => 'Converted At', 'type' => 'Date'),
        'convertedBy'        => array('label' => 'Converted By', 'type' => 'Text'),
        
        // Marketing & Campaign
        'campaign'           => array('label' => 'Campaign', 'type' => 'Pick List'),
        'source'             => array('label' => 'Source', 'type' => 'Pick List'),
        'subSource'          => array('label' => 'Sub Source', 'type' => 'Text'),
        'campaignActivities' => array('label' => 'Campaign Activities', 'type' => 'Text'),
        'importedBy'         => array('label' => 'Imported By', 'type' => 'Text'),
        
        // UTM Parameters
        'utmSource'          => array('label' => 'UTM Source', 'type' => 'Text'),
        'utmCampaign'        => array('label' => 'UTM Campaign', 'type' => 'Text'),
        'utmMedium'          => array('label' => 'UTM Medium', 'type' => 'Text'),
        'utmContent'         => array('label' => 'UTM Content', 'type' => 'Text'),
        'utmTerm'            => array('label' => 'UTM Term', 'type' => 'Text'),
        
        // Lead Management
        'score'              => array('label' => 'Score', 'type' => 'Number'),
        'taskDueOn'          => array('label' => 'Task Due On', 'type' => 'Date'),
        'meetingScheduledOn' => array('label' => 'Meeting Scheduled On', 'type' => 'Date'),
        'latestActivityCreatedAt' => array('label' => 'Latest Activity On', 'type' => 'Date'),
        'isNew'              => array('label' => 'Is New', 'type' => 'Boolean'),
        'aging'              => array('label' => 'Lead Aging (Days)', 'type' => 'Number'),
        
        // Custom Fields
        'timezone'           => array('label' => 'Timezone', 'type' => 'Pick List'),
        'cfDelhiveryStatus'  => array('label' => 'Delhivery Status', 'type' => 'Text'),
        'cfMailjetTag'       => array('label' => 'Mailjet Tag', 'type' => 'Text'),
        'cfType'             => array('label' => 'Type', 'type' => 'Text'),
        
        // Legacy/Deprecated fields (kept for compatibility)
        'description'        => array('label' => 'Description (Note)', 'type' => 'Text'),
        'zipCode'            => array('label' => 'Zip Code', 'type' => 'Text'),
    );
}

/**
 * Auto-mapping logic variations
 */
function kylas_crm_get_auto_mapping_logic() {
    return array(
        // Personal Information
        'firstName'   => array('first-name', 'fname', 'first_name', 'your-name', 'name', 'full-name', 'fullname', 'firstname'),
        'lastName'    => array('last-name', 'lname', 'last_name', 'surname', 'your-last-name', 'lastname'),
        'aboutYou'    => array('about-you', 'about_you', 'aboutyou', 'tell-me-about-yourself', 'about', 'your-about', 'aboutyou'),
        'salutation'  => array('salutation', 'title', 'prefix', 'mr-mrs-miss', 'greeting'),
        'emails'      => array('email', 'your-email', 'e-mail', 'your-e-mail', 'emails', 'email-address'),
        'phoneNumbers'=> array('phone', 'tel', 'mobile', 'contact', 'your-phone', 'your-tel', 'phone-number', 'phone-numbers', 'phonenumbers'),
        'designation' => array('designation', 'job-title', 'title', 'position', 'your-designation', 'jobtitle'),
        'dnd'         => array('dnd', 'do-not-disturb', 'donotdisturb', 'no-call', 'privacy'),
        
        // Address Information
        'address'     => array('address', 'street', 'your-address', 'street-address'),
        'city'        => array('city', 'location', 'your-city', 'town'),
        'state'       => array('state', 'province', 'your-state', 'region'),
        'zipcode'     => array('zip', 'zipcode', 'zip-code', 'pincode', 'pin-code', 'postal-code'),
        'country'     => array('country', 'your-country', 'nation'),
        'addressCoordinate' => array('address-coordinate', 'address_coordinate', 'location-coordinates', 'gps'),
        
        // Company Information
        'companyName' => array('company', 'org', 'organization', 'company-name', 'your-company', 'companyname', 'business-name'),
        'companyAddress' => array('company-address', 'company_address', 'business-address', 'office-address'),
        'companyCity' => array('company-city', 'company_city', 'business-city', 'office-city'),
        'companyState' => array('company-state', 'company_state', 'business-state', 'office-state'),
        'companyZipcode' => array('company-zipcode', 'company_zipcode', 'business-zipcode', 'office-zipcode'),
        'companyCountry' => array('company-country', 'company_country', 'business-country', 'office-country'),
        'companyEmployees' => array('company-employees', 'company_employees', 'employees', 'staff-size', 'team-size'),
        'companyAnnualRevenue' => array('company-annual-revenue', 'company_annual_revenue', 'annual-revenue', 'revenue', 'turnover'),
        'companyWebsite' => array('company-website', 'company_website', 'business-website', 'office-website', 'website'),
        'companyPhones' => array('company-phones', 'company_phones', 'business-phone', 'office-phone', 'work-phone'),
        'companyIndustry' => array('company-industry', 'company_industry', 'industry', 'business-industry', 'sector'),
        'companyBusinessType' => array('company-business-type', 'company_business_type', 'business-type', 'business-type'),
        'companyAddressCoordinate' => array('company-address-coordinate', 'company_address_coordinate', 'office-coordinate', 'business-gps'),
        'department'  => array('department', 'dept', 'your-department', 'team'),
        
        // Social Media
        'facebook'    => array('facebook', 'fb', 'facebook-url', 'facebook-profile'),
        'twitter'     => array('twitter', 'tweet', 'twitter-url', 'twitter-handle'),
        'linkedIn'    => array('linkedin', 'linked-in', 'linkedin-url', 'linkedin-profile'),
        
        // Pipeline & Sales Information
        'pipeline'    => array('pipeline', 'sales-pipeline', 'deal-pipeline'),
        'pipelineStage' => array('pipeline-stage', 'pipeline_stage', 'stage', 'deal-stage'),
        'pipelineStageReason' => array('pipeline-stage-reason', 'pipeline_stage_reason', 'stage-reason', 'deal-reason'),
        'forecastingType' => array('forecasting-type', 'forecasting_type', 'forecast-type', 'prediction-type'),
        
        // Requirements & Products
        'requirementName' => array('requirement', 'requirement-name', 'requirement_name', 'subject', 'your-subject', 'need', 'requirementname'),
        'requirementCurrency' => array('requirement-currency', 'requirement_currency', 'currency', 'preferred-currency'),
        'requirementBudget' => array('requirement-budget', 'requirement_budget', 'budget', 'your-budget', 'price-range'),
        'expectedClosureOn' => array('expected-closure-on', 'expected_closure_on', 'closure-date', 'expected-date', 'target-date'),
        'actualClosureDate' => array('actual-closure-date', 'actual_closure_date', 'closed-date', 'completion-date'),
        'products'     => array('products', 'products-or-services', 'product', 'service', 'offering'),
        
        // Conversion Information
        'convertedAt' => array('converted-at', 'converted_at', 'conversion-date', 'converted-date'),
        'convertedBy' => array('converted-by', 'converted_by', 'conversion-agent', 'converted-by-agent'),
        
        // Marketing & Campaign
        'campaign'    => array('campaign', 'marketing-campaign', 'campaign-name'),
        'source'      => array('source', 'lead-source', 'referral-source', 'how-did-you-hear'),
        'subSource'   => array('sub-source', 'sub_source', 'sub-source', 'referral-detail'),
        'campaignActivities' => array('campaign-activities', 'campaign_activities', 'marketing-activities'),
        'importedBy'  => array('imported-by', 'imported_by', 'import-source', 'data-source'),
        
        // UTM Parameters
        'utmSource'   => array('utm-source', 'utm_source', 'source', 'utm-source'),
        'utmCampaign' => array('utm-campaign', 'utm_campaign', 'campaign', 'utm-campaign'),
        'utmMedium'   => array('utm-medium', 'utm_medium', 'medium', 'utm-medium'),
        'utmContent'  => array('utm-content', 'utm_content', 'content', 'utm-content'),
        'utmTerm'     => array('utm-term', 'utm_term', 'term', 'utm-term'),
        
        // Lead Management
        'score'       => array('score', 'lead-score', 'rating', 'priority-score'),
        'taskDueOn'   => array('task-due-on', 'task_due_on', 'follow-up-date', 'task-date'),
        'meetingScheduledOn' => array('meeting-scheduled-on', 'meeting_scheduled_on', 'meeting-date', 'appointment-date'),
        'latestActivityCreatedAt' => array('latest-activity-created-at', 'latest_activity_created_at', 'last-activity', 'recent-activity'),
        'isNew'       => array('is-new', 'is_new', 'new-lead', 'new-flag'),
        'aging'       => array('aging', 'lead-aging', 'age', 'days-in-system'),
        
        // Custom Fields
        'timezone'    => array('timezone', 'time-zone', 'tz', 'your-timezone'),
        'cfDelhiveryStatus' => array('delhivery-status', 'delhivery_status', 'delivery-status', 'shipping-status'),
        'cfMailjetTag' => array('mailjet-tag', 'mailjet_tag', 'email-tag', 'mailing-list-tag'),
        'cfType'      => array('type', 'lead-type', 'contact-type', 'record-type'),
        
        // Legacy fields (for compatibility)
        'description' => array('message', 'your-message', 'description', 'details', 'about', 'comments', 'enquiry', 'note'),
        'zipCode'     => array('zip-code', 'zip_code', 'zip', 'zipcode', 'pincode'),
    );
}
