-- we don't know how to generate root <with-no-name> (class Root) :(

grant alter, alter routine, create, create routine, create temporary tables, create view, delete, drop, event, execute, index, insert, lock tables, references, select, show view, trigger, update on anaanas_db.* to 'anaanas_@user2'@localhost;

create table ads_campaigns
(
    campaign_id           int auto_increment
        primary key,
    campaign_user_id      int unsigned                 not null,
    campaign_title        varchar(256)                 not null,
    campaign_start_date   datetime                     not null,
    campaign_end_date     datetime                     not null,
    campaign_budget       double                       not null,
    campaign_spend        double          default 0    not null,
    campaign_bidding      enum ('click', 'view')       not null,
    audience_countries    mediumtext                   not null,
    audience_gender       varchar(32)                  not null,
    audience_relationship varchar(64)                  not null,
    ads_title             varchar(255)                 null,
    ads_description       mediumtext                   null,
    ads_type              varchar(32)                  not null,
    ads_url               varchar(256)                 null,
    ads_page              int unsigned                 null,
    ads_group             int unsigned                 null,
    ads_event             int unsigned                 null,
    ads_placement         enum ('newsfeed', 'sidebar') not null,
    ads_image             varchar(256)                 not null,
    campaign_created_date datetime                     not null,
    campaign_is_active    enum ('0', '1') default '1'  not null,
    campaign_is_approved  enum ('0', '1') default '0'  not null,
    campaign_is_declined  enum ('0', '1') default '0'  not null,
    campaign_views        int unsigned    default '0'  not null,
    campaign_clicks       int unsigned    default '0'  not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index campaign_user_id
    on ads_campaigns (campaign_user_id);

create table ads_system
(
    ads_id         int auto_increment
        primary key,
    title          varchar(256) not null,
    place          varchar(32)  not null,
    ads_pages_ids  text         null,
    ads_groups_ids text         null,
    code           mediumtext   not null,
    time           datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table affiliates_payments
(
    payment_id   int auto_increment
        primary key,
    user_id      int unsigned not null,
    amount       varchar(32)  not null,
    method       varchar(64)  not null,
    method_value text         not null,
    time         datetime     not null,
    status       tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on affiliates_payments (user_id);

create table announcements
(
    announcement_id int auto_increment
        primary key,
    name            varchar(256) not null,
    title           varchar(256) not null,
    type            varchar(32)  not null,
    code            mediumtext   not null,
    start_date      datetime     not null,
    end_date        datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table announcements_users
(
    id              int unsigned auto_increment
        primary key,
    announcement_id int unsigned not null,
    user_id         int unsigned not null,
    constraint announcement_id_user_id
        unique (announcement_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table auto_connect
(
    id         int unsigned auto_increment
        primary key,
    type       varchar(32)  not null,
    country_id int unsigned not null,
    nodes_ids  text         not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index country_id
    on auto_connect (country_id);

create table bank_transfers
(
    transfer_id  int unsigned auto_increment
        primary key,
    user_id      int unsigned         not null,
    handle       varchar(32)          not null,
    package_id   int unsigned         null,
    post_id      int unsigned         null,
    plan_id      int unsigned         null,
    movie_id     int unsigned         null,
    price        float                null,
    bank_receipt varchar(256)         not null,
    time         datetime             not null,
    status       tinyint(1) default 0 not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index package_id
    on bank_transfers (package_id);

create index post_id
    on bank_transfers (post_id);

create index user_id
    on bank_transfers (user_id);

create table blacklist
(
    node_id      int unsigned auto_increment
        primary key,
    node_type    enum ('ip', 'email', 'username') not null,
    node_value   varchar(64)                      not null,
    created_time datetime                         not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index node_value
    on blacklist (node_value);

create table blogs_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null,
    slug                 varchar(255)             null,
    icon                 varchar(255)             null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on blogs_categories (category_parent_id);

create table coinpayments_transactions
(
    transaction_id     int unsigned auto_increment
        primary key,
    transaction_txn_id text             null,
    user_id            int unsigned     not null,
    amount             varchar(32)      not null,
    product            text             not null,
    created_at         datetime         not null,
    last_update        datetime         not null,
    status             tinyint unsigned not null,
    status_message     text             null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on coinpayments_transactions (user_id);

create table conversations
(
    conversation_id int unsigned auto_increment
        primary key,
    last_message_id int unsigned    not null,
    color           varchar(32)     null,
    node_id         int unsigned    null,
    node_type       varchar(128)    null,
    post_id         bigint unsigned null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index last_message_id
    on conversations (last_message_id);

create table conversations_calls_audio
(
    call_id         int unsigned auto_increment
        primary key,
    from_user_id    int unsigned                not null,
    from_user_token mediumtext                  not null,
    to_user_id      int unsigned                not null,
    to_user_token   mediumtext                  not null,
    room            varchar(64)                 not null,
    answered        enum ('0', '1') default '0' not null,
    declined        enum ('0', '1') default '0' not null,
    created_time    datetime                    not null,
    updated_time    datetime                    not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index from_user_id
    on conversations_calls_audio (from_user_id);

create index to_user_id
    on conversations_calls_audio (to_user_id);

create table conversations_calls_video
(
    call_id         int unsigned auto_increment
        primary key,
    from_user_id    int unsigned                not null,
    from_user_token text                        not null,
    to_user_id      int unsigned                not null,
    to_user_token   text                        not null,
    room            varchar(64)                 not null,
    answered        enum ('0', '1') default '0' not null,
    declined        enum ('0', '1') default '0' not null,
    created_time    datetime                    not null,
    updated_time    datetime                    not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index from_user_id
    on conversations_calls_video (from_user_id);

create index to_user_id
    on conversations_calls_video (to_user_id);

create table conversations_messages
(
    message_id      int unsigned auto_increment
        primary key,
    conversation_id int unsigned not null,
    user_id         int unsigned not null,
    message         longtext     not null,
    image           varchar(256) not null,
    voice_note      varchar(256) not null,
    time            datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index conversation_id
    on conversations_messages (conversation_id);

create index user_id
    on conversations_messages (user_id);

create table conversations_users
(
    id              int unsigned auto_increment
        primary key,
    conversation_id int unsigned                not null,
    user_id         int unsigned                not null,
    seen            enum ('0', '1') default '0' not null,
    typing          enum ('0', '1') default '0' not null,
    deleted         enum ('0', '1') default '0' not null,
    constraint conversation_id_user_id
        unique (conversation_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table custom_fields
(
    field_id        int unsigned auto_increment
        primary key,
    field_for       varchar(64)                 not null,
    type            varchar(32)                 not null,
    select_options  mediumtext                  not null,
    label           varchar(256)                not null,
    description     mediumtext                  not null,
    place           varchar(32)                 not null,
    length          int             default 32  not null,
    field_order     int             default 1   not null,
    is_link         enum ('0', '1') default '0' not null,
    mandatory       enum ('0', '1') default '0' not null,
    in_registration enum ('0', '1') default '0' not null,
    in_profile      enum ('0', '1') default '0' not null,
    in_search       enum ('0', '1') default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table custom_fields_values
(
    value_id  int unsigned auto_increment
        primary key,
    value     mediumtext   not null,
    field_id  int unsigned not null,
    node_id   int unsigned not null,
    node_type varchar(64)  not null,
    constraint field_id_node_id_node_type
        unique (field_id, node_id, node_type)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index value
    on custom_fields_values (value(20));

create table developers_apps
(
    app_id           int unsigned auto_increment
        primary key,
    app_user_id      int unsigned not null,
    app_category_id  int unsigned not null,
    app_auth_id      varchar(128) not null,
    app_auth_secret  varchar(128) not null,
    app_name         varchar(256) not null,
    app_domain       varchar(256) not null,
    app_redirect_url varchar(256) not null,
    app_description  mediumtext   not null,
    app_icon         varchar(256) not null,
    app_date         datetime     not null,
    constraint app_auth_id
        unique (app_auth_id),
    constraint app_auth_secret
        unique (app_auth_secret)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index app_category_id
    on developers_apps (app_category_id);

create index app_user_id
    on developers_apps (app_user_id);

create table developers_apps_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on developers_apps_categories (category_parent_id);

create table developers_apps_users
(
    id                int unsigned auto_increment
        primary key,
    app_id            int unsigned not null,
    user_id           int unsigned not null,
    auth_key          varchar(128) not null,
    access_token      varchar(128) null,
    access_token_date datetime     null,
    constraint app_id_user_id
        unique (app_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table emojis
(
    emoji_id     int unsigned auto_increment
        primary key,
    unicode_char varchar(256) not null,
    class        varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table events
(
    event_id                       int unsigned auto_increment
        primary key,
    event_privacy                  enum ('secret', 'closed', 'public') default 'public' null,
    event_admin                    int unsigned                                         not null,
    event_page_id                  int unsigned                                         null,
    event_category                 int unsigned                                         not null,
    event_title                    varchar(256)                                         not null,
    event_location                 varchar(256)                                         null,
    event_description              mediumtext                                           not null,
    event_start_date               datetime                                             not null,
    event_end_date                 datetime                                             not null,
    event_publish_enabled          enum ('0', '1')                     default '1'      not null,
    event_publish_approval_enabled enum ('0', '1')                     default '0'      not null,
    event_cover                    varchar(256)                                         null,
    event_cover_id                 int unsigned                                         null,
    event_cover_position           varchar(256)                                         null,
    event_album_covers             int                                                  null,
    event_album_timeline           int                                                  null,
    event_pinned_post              int                                                  null,
    event_invited                  int unsigned                        default '0'      not null,
    event_interested               int unsigned                        default '0'      not null,
    event_going                    int unsigned                        default '0'      not null,
    chatbox_enabled                enum ('0', '1')                     default '0'      not null,
    chatbox_conversation_id        int unsigned                                         null,
    event_tickets_link             varchar(256)                                         null,
    event_prices                   text                                                 null,
    event_rate                     float                               default 0        not null,
    event_date                     datetime                                             not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index event_admin
    on events (event_admin);

create index event_album_covers
    on events (event_album_covers);

create index event_album_timeline
    on events (event_album_timeline);

create index event_category
    on events (event_category);

create index event_cover_id
    on events (event_cover_id);

create index event_date
    on events (event_date);

create index event_title_idx
    on events (event_title);

create table events_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on events_categories (category_parent_id);

create table events_members
(
    id            int unsigned auto_increment
        primary key,
    event_id      int unsigned                not null,
    user_id       int unsigned                not null,
    is_invited    enum ('0', '1') default '0' null,
    is_interested enum ('0', '1') default '0' null,
    is_going      enum ('0', '1') default '0' null,
    constraint event_id_user_id
        unique (event_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table followings
(
    id            int unsigned auto_increment
        primary key,
    user_id       int unsigned                not null,
    following_id  int unsigned                not null,
    points_earned enum ('0', '1') default '0' not null,
    time          datetime                    null,
    constraint user_id_following_id
        unique (user_id, following_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index following_id
    on followings (following_id);

create index user_id
    on followings (user_id);

create table forums
(
    forum_id          int unsigned auto_increment
        primary key,
    forum_section     int unsigned             not null,
    forum_name        varchar(256)             not null,
    forum_description mediumtext               null,
    forum_order       int unsigned default '1' not null,
    forum_threads     int unsigned default '0' not null,
    forum_replies     int unsigned default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index forum_section
    on forums (forum_section);

create table forums_replies
(
    reply_id  int unsigned auto_increment
        primary key,
    thread_id int unsigned not null,
    user_id   int unsigned not null,
    text      longtext     not null,
    time      datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index thread_id
    on forums_replies (thread_id);

create index user_id
    on forums_replies (user_id);

create table forums_threads
(
    thread_id  int unsigned auto_increment
        primary key,
    forum_id   int unsigned             not null,
    user_id    int unsigned             not null,
    title      varchar(256)             not null,
    text       longtext                 not null,
    replies    int unsigned default '0' not null,
    views      int unsigned default '0' not null,
    time       datetime                 not null,
    last_reply datetime                 null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index forum_id
    on forums_threads (forum_id);

create index user_id
    on forums_threads (user_id);

create table friends
(
    id          int unsigned auto_increment
        primary key,
    user_one_id int unsigned not null,
    user_two_id int unsigned not null,
    status      tinyint(1)   not null,
    constraint user_one_id_user_two_id
        unique (user_one_id, user_two_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index status
    on friends (status);

create index user_one_id
    on friends (user_one_id);

create index user_two_id
    on friends (user_two_id);

create table funding_payments
(
    payment_id   int auto_increment
        primary key,
    user_id      int unsigned not null,
    amount       varchar(32)  not null,
    method       varchar(64)  not null,
    method_value text         not null,
    time         datetime     not null,
    status       tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on funding_payments (user_id);

create table games
(
    game_id     int auto_increment
        primary key,
    title       varchar(256) not null,
    description mediumtext   not null,
    genres      mediumtext   null,
    source      mediumtext   not null,
    thumbnail   varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table games_genres
(
    genre_id          int unsigned auto_increment
        primary key,
    genre_name        varchar(256)             not null,
    genre_description text                     not null,
    genre_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table games_players
(
    id               int unsigned auto_increment
        primary key,
    game_id          int unsigned not null,
    user_id          int unsigned not null,
    last_played_time datetime     null,
    constraint game_id_user_id
        unique (game_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table gifts
(
    gift_id int unsigned auto_increment
        primary key,
    image   varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table `groups`
(
    group_id                       int unsigned auto_increment
        primary key,
    group_privacy                  enum ('secret', 'closed', 'public') default 'public' null,
    group_admin                    int unsigned                                         not null,
    group_category                 int unsigned                                         not null,
    group_name                     varchar(64)                                          not null,
    group_title                    varchar(256)                                         not null,
    group_description              mediumtext                                           not null,
    group_publish_enabled          enum ('0', '1')                     default '1'      not null,
    group_publish_approval_enabled enum ('0', '1')                     default '0'      not null,
    group_picture                  varchar(256)                                         null,
    group_picture_id               int unsigned                                         null,
    group_cover                    varchar(256)                                         null,
    group_cover_id                 int unsigned                                         null,
    group_cover_position           varchar(256)                                         null,
    group_album_pictures           int                                                  null,
    group_album_covers             int                                                  null,
    group_album_timeline           int                                                  null,
    group_pinned_post              int                                                  null,
    group_members                  int unsigned                        default '0'      not null,
    group_monetization_enabled     enum ('0', '1')                     default '0'      not null,
    group_monetization_min_price   float                               default 0        not null,
    group_monetization_plans       int unsigned                        default '0'      not null,
    chatbox_enabled                enum ('0', '1')                     default '0'      not null,
    chatbox_conversation_id        int unsigned                                         null,
    group_rate                     float                               default 0        not null,
    group_date                     datetime                                             not null,
    constraint group_name
        unique (group_name)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index group_admin
    on `groups` (group_admin);

create index group_album_covers
    on `groups` (group_album_covers);

create index group_album_pictures
    on `groups` (group_album_pictures);

create index group_album_timeline
    on `groups` (group_album_timeline);

create index group_category
    on `groups` (group_category);

create index group_cover_id
    on `groups` (group_cover_id);

create index group_date
    on `groups` (group_date);

create index group_name_idx
    on `groups` (group_name);

create index group_picture_id
    on `groups` (group_picture_id);

create index group_title_idx
    on `groups` (group_title);

create table groups_admins
(
    id       int unsigned auto_increment
        primary key,
    group_id int unsigned not null,
    user_id  int unsigned not null,
    constraint group_id_user_id
        unique (group_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table groups_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on groups_categories (category_parent_id);

create table groups_members
(
    id       int unsigned auto_increment
        primary key,
    group_id int unsigned                not null,
    user_id  int unsigned                not null,
    approved enum ('0', '1') default '0' not null,
    constraint group_id_user_id
        unique (group_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table hashtags
(
    hashtag_id int unsigned auto_increment
        primary key,
    hashtag    varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index hashtag
    on hashtags (hashtag);

create table hashtags_posts
(
    id         int unsigned auto_increment
        primary key,
    post_id    int unsigned not null,
    hashtag_id int unsigned not null,
    created_at datetime     null,
    constraint post_id_hashtag_id
        unique (post_id, hashtag_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table invitation_codes
(
    code_id      int unsigned auto_increment
        primary key,
    code         varchar(64)                 not null,
    created_by   int unsigned                not null,
    created_date datetime                    not null,
    used_by      int unsigned                null,
    used_date    datetime                    null,
    used         enum ('0', '1') default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index created_by
    on invitation_codes (created_by);

create index used_by
    on invitation_codes (used_by);

create table jobs_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on jobs_categories (category_parent_id);

create table log_commissions
(
    payment_id int auto_increment
        primary key,
    user_id    int unsigned not null,
    amount     float        not null,
    handle     varchar(128) not null,
    time       datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on log_commissions (user_id);

create table log_payments
(
    payment_id int auto_increment
        primary key,
    user_id    int unsigned not null,
    amount     float        not null,
    method     varchar(64)  not null,
    handle     varchar(128) not null,
    time       datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on log_payments (user_id);

create table log_sessions
(
    session_id         int unsigned auto_increment
        primary key,
    session_date       datetime                         not null,
    session_type       enum ('W', 'A', 'I') default 'W' not null,
    session_ip         varchar(64)                      not null,
    session_user_agent varchar(256)                     null,
    user_browser       varchar(64)                      null,
    user_os            varchar(64)                      null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index session_ip
    on log_sessions (session_ip);

create table market_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on market_categories (category_parent_id);

create table market_payments
(
    payment_id   int auto_increment
        primary key,
    user_id      int unsigned not null,
    amount       varchar(32)  not null,
    method       varchar(64)  not null,
    method_value text         not null,
    time         datetime     not null,
    status       tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on market_payments (user_id);

create table monetization_payments
(
    payment_id   int auto_increment
        primary key,
    user_id      int unsigned not null,
    amount       varchar(32)  not null,
    method       varchar(64)  not null,
    method_value text         not null,
    time         datetime     not null,
    status       tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on monetization_payments (user_id);

create table monetization_plans
(
    plan_id             int auto_increment
        primary key,
    node_id             int unsigned             not null,
    node_type           varchar(32)              not null,
    title               varchar(256)             not null,
    price               float                    not null,
    period_num          int unsigned             not null,
    period varchar (32) not null,
    custom_description  text                     null,
    plan_order          int unsigned default '1' not null,
    paypal_billing_plan varchar(256)             null,
    stripe_billing_plan varchar(256)             null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table movies
(
    movie_id      int unsigned auto_increment
        primary key,
    source        text                        not null,
    source_type   varchar(64)                 not null,
    title         varchar(256)                not null,
    description   text                        null,
    imdb_url      text                        null,
    stars         text                        null,
    release_year  int                         null,
    duration      int                         null,
    genres        mediumtext                  null,
    poster        varchar(256)                null,
    views         int unsigned    default '0' not null,
    is_paid       enum ('0', '1') default '0' not null,
    price         float           default 0   not null,
    available_for int             default 0   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table movies_genres
(
    genre_id          int unsigned auto_increment
        primary key,
    genre_name        varchar(256)             not null,
    genre_description text                     not null,
    genre_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table movies_payments
(
    id           int unsigned auto_increment
        primary key,
    movie_id     int unsigned not null,
    user_id      int unsigned not null,
    payment_time datetime     not null,
    constraint move_id_user_id
        unique (movie_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table notifications
(
    notification_id int unsigned auto_increment
        primary key,
    to_user_id      int unsigned                         not null,
    from_user_id    int unsigned                         not null,
    from_user_type  enum ('user', 'page') default 'user' not null,
    action          varchar(256)                         not null,
    node_type       varchar(256)                         not null,
    node_url        varchar(256)                         not null,
    notify_id       varchar(256)                         null,
    message         mediumtext                           null,
    time            datetime                             null,
    seen            enum ('0', '1')       default '0'    not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index from_user_id
    on notifications (from_user_id, from_user_type);

create index to_user_id
    on notifications (to_user_id);

create table offers_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on offers_categories (category_parent_id);

create table orders
(
    order_id         int unsigned auto_increment
        primary key,
    order_hash       varchar(128)                                                                               not null,
    is_digital       enum ('0', '1')                                                           default '0'      not null,
    seller_id        int unsigned                                                                               not null,
    buyer_id         int unsigned                                                                               not null,
    buyer_address_id int unsigned                                                                               not null,
    sub_total        float unsigned                                                            default '0'      not null,
    commission       float unsigned                                                                             not null,
    status           enum ('placed', 'canceled', 'accepted', 'packed', 'shipped', 'delivered') default 'placed' not null,
    tracking_link    mediumtext                                                                                 null,
    tracking_number  mediumtext                                                                                 null,
    insert_time      datetime                                                                                   not null,
    update_time      datetime                                                                                   not null
)
    collate = utf8mb4_general_ci;

create index buyer_address_id
    on orders (buyer_address_id);

create index buyer_id
    on orders (buyer_id);

create index seller_id
    on orders (seller_id);

create table orders_items
(
    id              int auto_increment
        primary key,
    order_id        int unsigned   not null,
    product_post_id int unsigned   not null,
    quantity        int unsigned   not null,
    price           float unsigned not null
)
    collate = utf8mb4_general_ci;

create index order_id
    on orders_items (order_id);

create index product_post_id
    on orders_items (product_post_id);

create table packages
(
    package_id                   int auto_increment
        primary key,
    name                         varchar(256)                not null,
    price                        varchar(32)                 not null,
    old_price                    varchar(100)                null,
    period_num                   int unsigned                not null,
    period varchar (32) not null,
    color                        varchar(32)                 not null,
    icon                         varchar(256)                not null,
    package_permissions_group_id int unsigned    default '0' not null,
    allowed_blogs_categories     int             default 0   not null,
    allowed_videos_categories    int             default 0   not null,
    allowed_products             int             default 0   not null,
    verification_badge_enabled   enum ('0', '1') default '0' not null,
    boost_posts_enabled          enum ('0', '1') default '0' not null,
    free_posts                   int             default 0   not null,
    boost_posts                  int unsigned                not null,
    posts_in_month               int             default 0   not null,
    boost_pages_enabled          enum ('0', '1') default '0' not null,
    boost_pages                  int unsigned                not null,
    custom_description           text                        null,
    package_order                int unsigned    default '1' not null,
    paypal_billing_plan          varchar(256)                null,
    stripe_billing_plan          varchar(256)                null,
    boost_in_month               int             default 0   not null,
    bonus                        int             default 0   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index package_permissions_group_id
    on packages (package_permissions_group_id);

create table packages_payments
(
    payment_id    int auto_increment
        primary key,
    payment_date  datetime       not null,
    package_name  varchar(256)   not null,
    package_price float unsigned not null,
    user_id       int unsigned   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on packages_payments (user_id);

create table pages
(
    page_id                     int unsigned auto_increment
        primary key,
    page_admin                  int unsigned                not null,
    page_category               int unsigned                not null,
    page_name                   varchar(64)                 not null,
    page_title                  varchar(256)                not null,
    page_picture                varchar(256)                null,
    page_picture_id             int unsigned                null,
    page_cover                  varchar(256)                null,
    page_cover_id               int unsigned                null,
    page_cover_position         varchar(256)                null,
    page_album_pictures         int unsigned                null,
    page_album_covers           int unsigned                null,
    page_album_timeline         int unsigned                null,
    page_pinned_post            int unsigned                null,
    page_verified               enum ('0', '1') default '0' not null,
    page_tips_enabled           enum ('0', '1') default '0' not null,
    page_boosted                enum ('0', '1') default '0' not null,
    page_boosted_by             int unsigned                null,
    page_company                varchar(256)                null,
    page_phone                  varchar(256)                null,
    page_website                varchar(256)                null,
    page_location               varchar(256)                null,
    page_country                int unsigned                not null,
    page_description            mediumtext                  not null,
    page_action_text            varchar(32)                 null,
    page_action_color           varchar(32)                 null,
    page_action_url             varchar(256)                null,
    page_social_facebook        varchar(256)                null,
    page_social_twitter         varchar(256)                null,
    page_social_youtube         varchar(256)                null,
    page_social_instagram       varchar(256)                null,
    page_social_linkedin        varchar(256)                null,
    page_social_vkontakte       varchar(256)                null,
    page_likes                  int unsigned    default '0' not null,
    page_monetization_enabled   enum ('0', '1') default '0' not null,
    page_monetization_min_price float           default 0   not null,
    page_monetization_plans     int unsigned    default '0' not null,
    page_rate                   float           default 0   not null,
    page_date                   datetime                    not null,
    constraint page_name
        unique (page_name)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index page_admin
    on pages (page_admin);

create index page_album_covers
    on pages (page_album_covers);

create index page_album_pictures
    on pages (page_album_pictures);

create index page_album_timeline
    on pages (page_album_timeline);

create index page_boosted
    on pages (page_boosted);

create index page_category
    on pages (page_category);

create index page_country
    on pages (page_country);

create index page_cover_id
    on pages (page_cover_id);

create index page_date
    on pages (page_date);

create index page_name_idx
    on pages (page_name);

create index page_picture_id
    on pages (page_picture_id);

create index page_title_idx
    on pages (page_title);

create table pages_admins
(
    id      int unsigned auto_increment
        primary key,
    page_id int unsigned not null,
    user_id int unsigned not null,
    constraint page_id_user_id
        unique (page_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table pages_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on pages_categories (category_parent_id);

create table pages_invites
(
    id           int unsigned auto_increment
        primary key,
    page_id      int unsigned not null,
    user_id      int unsigned not null,
    from_user_id int unsigned not null,
    constraint page_id_user_id_from_user_id
        unique (page_id, user_id, from_user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table pages_likes
(
    id      int unsigned auto_increment
        primary key,
    page_id int unsigned not null,
    user_id int unsigned not null,
    constraint page_id_user_id
        unique (page_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table permissions_groups
(
    permissions_group_id         int unsigned auto_increment
        primary key,
    permissions_group_title      varchar(255)                not null,
    pages_permission             enum ('0', '1') default '0' not null,
    groups_permission            enum ('0', '1') default '0' not null,
    events_permission            enum ('0', '1') default '0' not null,
    posts_permission             enum ('0', '1') default '0' not null,
    blogs_permission             enum ('0', '1') default '0' not null,
    market_permission            enum ('0', '1') default '0' not null,
    offers_permission            enum ('0', '1') default '0' not null,
    jobs_permission              enum ('0', '1') default '0' not null,
    forums_permission            enum ('0', '1') default '0' not null,
    movies_permission            enum ('0', '1') default '0' not null,
    games_permission             enum ('0', '1') default '0' not null,
    gifts_permission             enum ('0', '1') default '0' not null,
    blogs_permission_read        enum ('0', '1') default '0' not null,
    videos_permission_read       enum ('0', '1') default '0' not null,
    stories_permission           enum ('0', '1') default '0' not null,
    colored_posts_permission     enum ('0', '1') default '0' not null,
    activity_posts_permission    enum ('0', '1') default '0' not null,
    polls_posts_permission       enum ('0', '1') default '0' not null,
    geolocation_posts_permission enum ('0', '1') default '0' not null,
    gif_posts_permission         enum ('0', '1') default '0' not null,
    anonymous_posts_permission   enum ('0', '1') default '0' not null,
    invitation_permission        enum ('0', '1') default '0' not null,
    audio_call_permission        enum ('0', '1') default '0' not null,
    video_call_permission        enum ('0', '1') default '0' not null,
    live_permission              enum ('0', '1') default '0' not null,
    videos_upload_permission     enum ('0', '1') default '0' not null,
    audios_upload_permission     enum ('0', '1') default '0' not null,
    files_upload_permission      enum ('0', '1') default '0' not null,
    ads_permission               enum ('0', '1') default '0' not null,
    fundings_permission          enum ('0', '1') default '0' not null,
    monetization_permission      enum ('0', '1') default '0' not null,
    tips_permission              enum ('0', '1') default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table points_payments
(
    payment_id   int auto_increment
        primary key,
    user_id      int unsigned not null,
    amount       varchar(32)  not null,
    method       varchar(64)  not null,
    method_value text         not null,
    time         datetime     not null,
    status       tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on points_payments (user_id);

create table posts
(
    post_id              int unsigned auto_increment
        primary key,
    user_id              int unsigned                              not null,
    user_type            enum ('user', 'page')                     not null,
    in_group             enum ('0', '1') default '0'               not null,
    group_id             int unsigned                              null,
    group_approved       enum ('0', '1') default '1'               not null,
    in_event             enum ('0', '1') default '0'               not null,
    event_id             int unsigned                              null,
    event_approved       enum ('0', '1') default '1'               not null,
    in_wall              enum ('0', '1') default '0'               not null,
    wall_id              int unsigned                              null,
    post_type            varchar(32)                               not null,
    colored_pattern      int unsigned                              null,
    origin_id            int unsigned                              null,
    time                 datetime                                  not null,
    location             varchar(256)                              null,
    privacy              varchar(32)                               not null,
    text                 longtext                                  null,
    feeling_action       varchar(32)                               null,
    feeling_value        varchar(256)                              null,
    boosted              enum ('0', '1') default '0'               not null,
    boosted_by           int unsigned                              null,
    comments_disabled    enum ('0', '1') default '0'               not null,
    is_hidden            enum ('0', '1') default '0'               not null,
    for_adult            enum ('0', '1') default '0'               not null,
    is_anonymous         enum ('0', '1') default '0'               not null,
    reaction_like_count  int unsigned    default '0'               not null,
    reaction_love_count  int unsigned    default '0'               not null,
    reaction_haha_count  int unsigned    default '0'               not null,
    reaction_yay_count   int unsigned    default '0'               not null,
    reaction_wow_count   int unsigned    default '0'               not null,
    reaction_sad_count   int unsigned    default '0'               not null,
    reaction_angry_count int unsigned    default '0'               not null,
    comments             int unsigned    default '0'               not null,
    shares               int unsigned    default '0'               not null,
    views                int unsigned    default '0'               not null,
    post_rate            float           default 0                 not null,
    points_earned        enum ('0', '1') default '0'               not null,
    tips_enabled         enum ('0', '1') default '0'               not null,
    for_subscriptions    enum ('0', '1') default '0'               not null,
    is_paid              enum ('0', '1') default '0'               not null,
    post_price           float unsigned  default '0'               not null,
    paid_text            text                                      null,
    processing           enum ('0', '1') default '0'               not null,
    pre_approved         enum ('0', '1') default '1'               not null,
    has_approved         enum ('0', '1') default '0'               not null,
    country_id           int                                       null,
    city_id              int                                       null,
    section_id           int                                       null,
    category_id          int                                       null,
    post_title           varchar(300)                              null,
    mobileNumber         varchar(100)                              null,
    attribute            longtext                                  null,
    attributeObjects     longtext                                  null,
    land_area_unit       int                                       null,
    building_area_unit   int                                       null,
    currency_id          int                                       null,
    lat                  varchar(20)                               null,
    lng                  varchar(20)                               null,
    is_free              int             default 1                 not null,
    package_id           int                                       null,
    deleted_at           date                                      null,
    is_republished       tinyint         default 0                 not null,
    old_time             datetime        default CURRENT_TIMESTAMP not null,
    max_ser_number       bigint unsigned default '0'               not null,
    slug                 varchar(255)                              null,
    email                varchar(100)                              null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index boosted
    on posts (boosted);

create index boosted_by
    on posts (boosted_by);

create index colored_pattern
    on posts (colored_pattern);

create index event_id
    on posts (event_id);

create index group_id
    on posts (group_id);

create index origin_id
    on posts (origin_id);

create index time
    on posts (time);

create index user_id
    on posts (user_id, user_type);

create index wall_id
    on posts (wall_id);

create table posts_articles
(
    article_id  int unsigned auto_increment
        primary key,
    post_id     int unsigned             not null,
    cover       varchar(256)             not null,
    title       varchar(256)             not null,
    text        text                     not null,
    category_id int unsigned             not null,
    tags        text                     not null,
    views       int unsigned default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_id
    on posts_articles (category_id);

create index post_id
    on posts_articles (post_id);

create index title_idx
    on posts_articles (title);

create table posts_audios
(
    audio_id int unsigned auto_increment
        primary key,
    post_id  int unsigned  not null,
    source   varchar(256)  not null,
    views    int default 0 not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_audios (post_id);

create table posts_boosted
(
    user_id   int                                not null,
    post_id   int                                not null,
    date_time datetime default CURRENT_TIMESTAMP not null,
    constraint unique_user_post
        unique (user_id, post_id)
);

create table posts_colored_patterns
(
    pattern_id         int unsigned auto_increment
        primary key,
    type               enum ('color', 'image') default 'color' not null,
    background_image   varchar(256)                            null,
    background_color_1 varchar(32)                             null,
    background_color_2 varchar(32)                             null,
    text_color         varchar(32)                             null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_comments
(
    comment_id           int unsigned auto_increment
        primary key,
    node_id              int unsigned                      not null,
    node_type            enum ('post', 'photo', 'comment') not null,
    user_id              int unsigned                      not null,
    user_type            enum ('user', 'page')             not null,
    text                 longtext                          not null,
    image                varchar(256)                      null,
    voice_note           varchar(256)                      null,
    time                 datetime                          not null,
    reaction_like_count  int unsigned    default '0'       not null,
    reaction_love_count  int unsigned    default '0'       not null,
    reaction_haha_count  int unsigned    default '0'       not null,
    reaction_yay_count   int unsigned    default '0'       not null,
    reaction_wow_count   int unsigned    default '0'       not null,
    reaction_sad_count   int unsigned    default '0'       not null,
    reaction_angry_count int unsigned    default '0'       not null,
    replies              int unsigned    default '0'       not null,
    points_earned        enum ('0', '1') default '0'       not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index node_id
    on posts_comments (node_id, node_type);

create index user_id
    on posts_comments (user_id, user_type);

create table posts_comments_reactions
(
    id            int unsigned auto_increment
        primary key,
    comment_id    int unsigned                   not null,
    user_id       int unsigned                   not null,
    reaction      varchar(32)     default 'like' null,
    reaction_time datetime                       null,
    points_earned enum ('0', '1') default '0'    not null,
    constraint comment_id_user_id
        unique (comment_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_files
(
    file_id int unsigned auto_increment
        primary key,
    post_id int unsigned not null,
    source  varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_files (post_id);

create table posts_funding
(
    funding_id      int unsigned auto_increment
        primary key,
    post_id         int unsigned    not null,
    title           varchar(256)    not null,
    amount          float default 0 not null,
    raised_amount   float default 0 not null,
    total_donations int   default 0 not null,
    cover_image     varchar(256)    not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_funding (post_id);

create table posts_funding_donors
(
    donation_id     int unsigned auto_increment
        primary key,
    user_id         int unsigned               not null,
    post_id         int unsigned               not null,
    donation_amount float unsigned default '0' not null,
    donation_time   datetime                   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_funding_donors (post_id);

create index user_id
    on posts_funding_donors (user_id);

create table posts_hidden
(
    id      int unsigned auto_increment
        primary key,
    post_id int unsigned not null,
    user_id int unsigned not null,
    constraint post_id_user_id
        unique (post_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_jobs
(
    job_id             int unsigned auto_increment
        primary key,
    post_id            int unsigned                not null,
    category_id        int unsigned                not null,
    title              varchar(100)                not null,
    location           varchar(100)                not null,
    salary_minimum     float unsigned              not null,
    salary_maximum     float unsigned              not null,
    pay_salary_per     varchar(100)                not null,
    type               varchar(100)                not null,
    question_1_type    varchar(100)                null,
    question_1_title   varchar(256)                null,
    question_1_choices text                        null,
    question_2_type    varchar(100)                null,
    question_2_title   varchar(256)                null,
    question_2_choices text                        null,
    question_3_type    varchar(100)                null,
    question_3_title   varchar(256)                null,
    question_3_choices text                        null,
    cover_image        varchar(256)                not null,
    available          enum ('0', '1') default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_id
    on posts_jobs (category_id);

create index post_id
    on posts_jobs (post_id);

create table posts_jobs_applications
(
    application_id    int unsigned auto_increment
        primary key,
    post_id           int unsigned    not null,
    user_id           int unsigned    not null,
    name              varchar(100)    not null,
    location          varchar(100)    not null,
    phone             varchar(100)    not null,
    email             varchar(100)    not null,
    work_place        varchar(100)    null,
    work_position     varchar(100)    null,
    work_description  text            null,
    work_from         varchar(100)    null,
    work_to           varchar(100)    null,
    work_now          enum ('0', '1') null,
    question_1_answer text            null,
    question_2_answer text            null,
    question_3_answer text            null,
    cv                varchar(256)    null,
    applied_time      datetime        not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_jobs_applications (post_id);

create index user_id
    on posts_jobs_applications (user_id);

create table posts_links
(
    link_id          int unsigned auto_increment
        primary key,
    post_id          int unsigned not null,
    source_url       text         not null,
    source_host      varchar(256) not null,
    source_title     text         not null,
    source_text      mediumtext   not null,
    source_thumbnail text         not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_links (post_id);

create table posts_live
(
    live_id            int unsigned auto_increment
        primary key,
    post_id            int unsigned                not null,
    video_thumbnail    varchar(256)                not null,
    agora_uid          int                         not null,
    agora_channel_name varchar(256)                not null,
    agora_resource_id  text                        null,
    agora_sid          varchar(256)                null,
    agora_file         text                        null,
    live_ended         enum ('0', '1') default '0' not null,
    live_recorded      enum ('0', '1') default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_live (post_id);

create table posts_live_users
(
    id      int unsigned auto_increment
        primary key,
    user_id int unsigned not null,
    post_id int unsigned not null,
    constraint user_id_post_id
        unique (user_id, post_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_media
(
    media_id         int unsigned auto_increment
        primary key,
    post_id          int          not null,
    source_url       mediumtext   not null,
    source_provider  varchar(256) not null,
    source_type      varchar(256) not null,
    source_title     text         null,
    source_text      mediumtext   null,
    source_html      mediumtext   null,
    source_thumbnail text         null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_media (post_id);

create table posts_offers
(
    offer_id         int unsigned auto_increment
        primary key,
    post_id          int unsigned               not null,
    category_id      int unsigned               not null,
    title            varchar(100)               not null,
    discount_type    varchar(32)                not null,
    discount_percent int unsigned               not null,
    discount_amount  varchar(100)               not null,
    buy_x            varchar(100)               not null,
    get_y            varchar(100)               not null,
    spend_x          varchar(100)               not null,
    amount_y         varchar(100)               not null,
    end_date         datetime                   null,
    price            float unsigned default '0' not null,
    thumbnail        varchar(256)               null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_id
    on posts_offers (category_id);

create index post_id
    on posts_offers (post_id);

create table posts_paid
(
    id      int unsigned auto_increment
        primary key,
    post_id int unsigned not null,
    user_id int unsigned not null,
    time    datetime     not null,
    constraint post_id_user_id
        unique (post_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_photos
(
    photo_id             int unsigned auto_increment
        primary key,
    post_id              int unsigned                not null,
    album_id             int unsigned                null,
    source               varchar(256)                not null,
    blur                 enum ('0', '1') default '0' not null,
    pinned               enum ('0', '1') default '0' not null,
    reaction_like_count  int unsigned    default '0' not null,
    reaction_love_count  int unsigned    default '0' not null,
    reaction_haha_count  int unsigned    default '0' not null,
    reaction_yay_count   int unsigned    default '0' not null,
    reaction_wow_count   int unsigned    default '0' not null,
    reaction_sad_count   int unsigned    default '0' not null,
    reaction_angry_count int unsigned    default '0' not null,
    comments             int unsigned    default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = COMPRESSED;

create index album_id
    on posts_photos (album_id);

create index post_id
    on posts_photos (post_id);

create table posts_photos_albums
(
    album_id  int unsigned auto_increment
        primary key,
    user_id   int unsigned                                                not null,
    user_type enum ('user', 'page')                                       not null,
    in_group  enum ('0', '1')                            default '0'      not null,
    group_id  int unsigned                                                null,
    in_event  enum ('0', '1')                            default '0'      not null,
    event_id  int unsigned                                                null,
    title     varchar(256)                                                not null,
    privacy   enum ('me', 'friends', 'public', 'custom') default 'public' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index event_id
    on posts_photos_albums (event_id);

create index group_id
    on posts_photos_albums (group_id);

create index user_id
    on posts_photos_albums (user_id, user_type);

create table posts_photos_reactions
(
    id            int unsigned auto_increment
        primary key,
    photo_id      int unsigned                   not null,
    user_id       int unsigned                   not null,
    reaction      varchar(32)     default 'like' not null,
    reaction_time datetime                       null,
    points_earned enum ('0', '1') default '0'    null,
    constraint user_id_photo_id
        unique (user_id, photo_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_polls
(
    poll_id int unsigned auto_increment
        primary key,
    post_id int unsigned             not null,
    votes   int unsigned default '0' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index post_id
    on posts_polls (post_id);

create table posts_polls_options
(
    option_id int unsigned auto_increment
        primary key,
    poll_id   int unsigned not null,
    text      varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index poll_id
    on posts_polls_options (poll_id);

create table posts_polls_options_users
(
    id        int unsigned auto_increment
        primary key,
    user_id   int unsigned not null,
    poll_id   int unsigned not null,
    option_id int unsigned not null,
    constraint user_id_poll_id
        unique (user_id, poll_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_products
(
    product_id           int unsigned auto_increment
        primary key,
    post_id              int unsigned                      not null,
    name                 varchar(256)                      not null,
    price                float unsigned      default '0'   not null,
    quantity             int unsigned        default '1'   not null,
    category_id          int unsigned                      not null,
    status               enum ('new', 'old') default 'new' not null,
    location             varchar(255)                      not null,
    available            enum ('0', '1')     default '1'   not null,
    is_digital           enum ('0', '1')     default '0'   not null,
    product_download_url text                              null,
    product_file_source  varchar(256)                      null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_id
    on posts_products (category_id);

create index post_id
    on posts_products (post_id);

create table posts_reactions
(
    id            int unsigned auto_increment
        primary key,
    post_id       int unsigned                   not null,
    user_id       int unsigned                   not null,
    reaction      varchar(32)     default 'like' not null,
    reaction_time datetime                       null,
    points_earned enum ('0', '1') default '0'    not null,
    constraint post_id_user_id
        unique (post_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_saved
(
    id      int unsigned auto_increment
        primary key,
    post_id int unsigned not null,
    user_id int unsigned not null,
    time    datetime     not null,
    constraint post_id_user_id
        unique (post_id, user_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table posts_videos
(
    video_id     int unsigned auto_increment
        primary key,
    post_id      int unsigned  not null,
    category_id  int unsigned  null,
    source       varchar(256)  not null,
    source_240p  varchar(256)  null,
    source_360p  varchar(256)  null,
    source_480p  varchar(256)  null,
    source_720p  varchar(256)  null,
    source_1080p varchar(256)  null,
    source_1440p varchar(256)  null,
    source_2160p varchar(256)  null,
    thumbnail    varchar(256)  null,
    views        int default 0 not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_id
    on posts_videos (category_id);

create index post_id
    on posts_videos (post_id);

create table posts_videos_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on posts_videos_categories (category_parent_id);

create table posts_views
(
    view_id   int unsigned auto_increment
        primary key,
    view_date datetime     not null,
    post_id   int unsigned not null,
    user_id   int unsigned null,
    guest_ip  varchar(64)  null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index guest_ip
    on posts_views (guest_ip);

create index post_id
    on posts_views (post_id);

create index user_id
    on posts_views (user_id);

create table reports
(
    report_id   int unsigned auto_increment
        primary key,
    user_id     int unsigned not null,
    node_id     int unsigned not null,
    node_type   varchar(32)  not null,
    category_id int unsigned not null,
    reason      text         not null,
    time        datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_id
    on reports (category_id);

create index node_id
    on reports (node_id);

create index user_id
    on reports (user_id);

create table reports_categories
(
    category_id          int unsigned auto_increment
        primary key,
    category_parent_id   int unsigned             not null,
    category_name        varchar(256)             not null,
    category_description text                     not null,
    category_order       int unsigned default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index category_parent_id
    on reports_categories (category_parent_id);

create table reviews
(
    review_id int unsigned auto_increment
        primary key,
    node_id   int unsigned not null,
    node_type varchar(32)  not null,
    user_id   int unsigned not null,
    rate      smallint     not null,
    review    text         not null,
    reply     text         null,
    time      datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index page_id
    on reviews (node_id);

create index user_id
    on reviews (user_id);

create table reviews_photos
(
    photo_id  int unsigned auto_increment
        primary key,
    review_id int unsigned not null,
    source    varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index review_id
    on reviews_photos (review_id);

create table section_categories
(
    id         bigint unsigned auto_increment
        primary key,
    section_id bigint unsigned not null,
    ar_name    varchar(200)    not null,
    en_name    varchar(200)    not null,
    icon       varchar(200)    not null,
    slug       varchar(100)    null
)
    collate = utf8mb4_general_ci;

create index section_id
    on section_categories (section_id);

create table sections
(
    id      bigint unsigned auto_increment
        primary key,
    ar_name varchar(200) not null,
    en_name varchar(200) not null,
    icon    varchar(250) not null,
    slug    varchar(100) null
)
    collate = utf8mb4_general_ci;

create table attribute_options
(
    id           bigint unsigned auto_increment
        primary key,
    section_id   bigint unsigned not null,
    category_id  bigint unsigned not null,
    attribute_id bigint unsigned not null,
    ar_name      varchar(100)    not null,
    en_name      varchar(100)    not null,
    constraint attribute_options_ibfk_1
        foreign key (section_id) references sections (id),
    constraint attribute_options_ibfk_2
        foreign key (category_id) references section_categories (id)
)
    collate = utf8mb4_general_ci;

create index attribute_id
    on attribute_options (attribute_id);

create index category_id
    on attribute_options (category_id);

create index section_id
    on attribute_options (section_id);

create table attributes
(
    id               bigint unsigned auto_increment
        primary key,
    parent_id        bigint unsigned   null,
    parent_option_id bigint unsigned   null,
    section_id       bigint unsigned   not null,
    category_id      bigint unsigned   not null,
    ar_name          varchar(200)      not null,
    en_name          varchar(200)      not null,
    key_name         varchar(100)      not null,
    input_type       varchar(100)      not null,
    multiselect      tinyint default 0 not null,
    constraint attributes_ibfk_1
        foreign key (section_id) references sections (id),
    constraint attributes_ibfk_2
        foreign key (category_id) references section_categories (id),
    constraint attributes_ibfk_3
        foreign key (parent_id) references attributes (id),
    constraint attributes_ibfk_4
        foreign key (parent_option_id) references attribute_options (id)
            on update cascade on delete cascade
)
    collate = utf8mb4_general_ci;

alter table attribute_options
    add constraint attribute_options_ibfk_3
        foreign key (attribute_id) references attributes (id);

create index category_id
    on attributes (category_id);

create index parent_id
    on attributes (parent_id);

create index parent_option_id
    on attributes (parent_option_id);

create index section_id
    on attributes (section_id);

create table shopping_cart
(
    id              int auto_increment
        primary key,
    user_id         int unsigned             not null,
    product_post_id int unsigned             not null,
    quantity        int unsigned default '1' not null
)
    collate = utf8mb4_general_ci;

create index product_post_id
    on shopping_cart (product_post_id);

create index user_id
    on shopping_cart (user_id);

create table sneak_peaks
(
    id        int unsigned auto_increment
        primary key,
    user_id   int unsigned not null,
    node_id   int unsigned not null,
    node_type varchar(32)  not null,
    time      datetime     not null,
    constraint user_id_node_id_node_type
        unique (user_id, node_id, node_type)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table static_pages
(
    page_id        int unsigned auto_increment
        primary key,
    page_url       varchar(64)                 not null,
    page_title     varchar(256)                not null,
    page_text      mediumtext                  not null,
    page_in_footer enum ('0', '1') default '1' not null,
    page_order     int unsigned    default '1' not null,
    constraint page_url
        unique (page_url)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table stickers
(
    sticker_id int unsigned auto_increment
        primary key,
    image      varchar(256) not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table stories
(
    story_id int unsigned auto_increment
        primary key,
    user_id  int unsigned                not null,
    is_ads   enum ('0', '1') default '0' not null,
    time     datetime                    not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on stories (user_id);

create table stories_media
(
    media_id int unsigned auto_increment
        primary key,
    story_id int unsigned                not null,
    source   varchar(256)                not null,
    is_photo enum ('0', '1') default '1' not null,
    text     text                        not null,
    time     datetime                    not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index story_id
    on stories_media (story_id);

create table subscribers
(
    id        int unsigned auto_increment
        primary key,
    user_id   int unsigned not null,
    node_id   int unsigned not null,
    node_type varchar(32)  not null,
    plan_id   int unsigned not null,
    time      datetime     not null,
    constraint user_id_node_id_node_type
        unique (user_id, node_id, node_type)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table system_countries
(
    country_id    int unsigned auto_increment
        primary key,
    country_code  varchar(2)                  not null,
    country_name  varchar(64)                 not null,
    phone_code    varchar(8)                  null,
    country_vat   int unsigned                null,
    `default`     enum ('0', '1')             not null,
    enabled       enum ('0', '1') default '1' not null,
    country_order int unsigned    default '1' not null,
    ar_name       varchar(250)                null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table cities
(
    id          int unsigned auto_increment
        primary key,
    country_id  int unsigned    not null,
    en_name     varchar(25)     not null,
    ar_name     varchar(25)     not null,
    index_order int default 100 null,
    constraint city
        unique (country_id, en_name, ar_name),
    constraint cities_ibfk_1
        foreign key (country_id) references system_countries (country_id)
            on update cascade on delete cascade
)
    collate = utf8mb4_general_ci
    row_format = COMPACT;

create index country_id
    on cities (country_id);

create table system_currencies
(
    currency_id int unsigned auto_increment
        primary key,
    country_id  int                                   null,
    name        varchar(256)                          not null,
    code        varchar(32)                           not null,
    symbol      varchar(32)                           not null,
    dir         enum ('left', 'right') default 'left' not null,
    `default`   enum ('0', '1')                       not null,
    enabled     enum ('0', '1')        default '1'    not null,
    rate        decimal(10, 4)                        not null,
    last_update timestamp                             not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table system_genders
(
    gender_id    int unsigned auto_increment
        primary key,
    gender_name  varchar(64)   not null,
    gender_order int default 1 not null,
    constraint name
        unique (gender_name)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table system_languages
(
    language_id    int unsigned auto_increment
        primary key,
    code           varchar(32)              not null,
    title          varchar(256)             not null,
    flag           varchar(256)             not null,
    dir            enum ('LTR', 'RTL')      not null,
    `default`      enum ('0', '1')          not null,
    enabled        enum ('0', '1')          not null,
    language_order int unsigned default '1' not null,
    constraint code
        unique (code)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table system_options
(
    option_id    int unsigned auto_increment
        primary key,
    option_name  varchar(128) not null,
    option_value text         not null,
    constraint option_name
        unique (option_name)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table system_reactions
(
    reaction_id    int unsigned auto_increment
        primary key,
    reaction       varchar(32)                 not null,
    title          varchar(32)                 not null,
    color          varchar(128)                null,
    image          varchar(256)                not null,
    reaction_order int unsigned    default '1' not null,
    enabled        enum ('0', '1') default '1' not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table system_themes
(
    theme_id  int unsigned auto_increment
        primary key,
    name      varchar(64)     not null,
    `default` enum ('0', '1') not null,
    enabled   enum ('0', '1') not null,
    constraint name
        unique (name)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users
(
    user_id                         int unsigned auto_increment
        primary key,
    user_master_account             int                              default 0                 not null,
    user_group                      tinyint unsigned                 default '3'               not null,
    user_group_custom               int                              default 0                 not null,
    user_demo                       enum ('0', '1')                  default '0'               not null,
    user_name                       varchar(64)                                                not null,
    user_email                      varchar(64)                                                not null,
    user_email_verified             enum ('0', '1')                  default '0'               not null,
    user_email_verification_code    varchar(64)                                                null,
    user_phone                      varchar(64)                                                null,
    user_phone_verified             enum ('0', '1')                  default '0'               not null,
    user_phone_verification_code    varchar(64)                                                null,
    user_password                   varchar(64)                                                not null,
    user_two_factor_enabled         enum ('0', '1')                  default '0'               not null,
    user_two_factor_type            enum ('email', 'sms', 'google')                            null,
    user_two_factor_key             varchar(64)                                                null,
    user_two_factor_gsecret         varchar(64)                                                null,
    user_activated                  enum ('0', '1')                  default '0'               not null,
    user_reseted                    enum ('0', '1')                  default '0'               not null,
    user_reset_key                  varchar(64)                                                null,
    user_subscribed                 enum ('0', '1')                  default '0'               not null,
    user_package                    int unsigned                                               null,
    user_package_videos_categories  text                                                       null,
    user_package_blogs_categories   text                                                       null,
    user_subscription_date          datetime                                                   null,
    user_boosted_posts              int unsigned                     default '0'               not null,
    user_boosted_pages              int unsigned                     default '0'               not null,
    user_started                    enum ('0', '1')                  default '0'               not null,
    user_verified                   enum ('0', '1')                  default '0'               not null,
    user_banned                     enum ('0', '1')                  default '0'               not null,
    user_banned_message             text                                                       null,
    user_live_requests_counter      int unsigned                     default '0'               not null,
    user_live_requests_lastid       int unsigned                     default '0'               not null,
    user_live_messages_counter      int unsigned                     default '0'               not null,
    user_live_messages_lastid       int unsigned                     default '0'               not null,
    user_live_notifications_counter int unsigned                     default '0'               not null,
    user_live_notifications_lastid  int unsigned                     default '0'               not null,
    user_latitude                   varchar(256)                     default '0'               not null,
    user_longitude                  varchar(256)                     default '0'               not null,
    user_location_updated           datetime                                                   null,
    user_firstname                  varchar(256)                                               not null,
    user_lastname                   varchar(256)                                               null,
    user_gender                     int unsigned                                               null,
    user_picture                    varchar(255)                                               null,
    user_picture_id                 int unsigned                                               null,
    user_cover                      varchar(256)                                               null,
    user_cover_id                   int unsigned                                               null,
    user_cover_position             varchar(256)                                               null,
    user_album_pictures             int unsigned                                               null,
    user_album_covers               int unsigned                                               null,
    user_album_timeline             int unsigned                                               null,
    user_pinned_post                int unsigned                                               null,
    user_registered                 datetime                                                   null,
    user_last_seen                  timestamp                        default CURRENT_TIMESTAMP not null,
    user_first_failed_login         datetime                                                   null,
    user_failed_login_ip            varchar(64)                                                null,
    user_failed_login_count         int unsigned                     default '0'               not null,
    user_country                    int unsigned                                               null,
    user_birthdate                  date                                                       null,
    user_relationship               varchar(256)                                               null,
    user_biography                  text                                                       null,
    user_website                    varchar(256)                                               null,
    user_work_title                 varchar(256)                                               null,
    user_work_place                 varchar(256)                                               null,
    user_work_url                   varchar(256)                                               null,
    user_current_city               varchar(256)                                               null,
    user_hometown                   varchar(256)                                               null,
    user_edu_major                  varchar(256)                                               null,
    user_edu_school                 varchar(256)                                               null,
    user_edu_class                  varchar(256)                                               null,
    user_social_facebook            varchar(256)                                               null,
    user_social_twitter             varchar(256)                                               null,
    user_social_youtube             varchar(256)                                               null,
    user_social_instagram           varchar(256)                                               null,
    user_social_twitch              varchar(256)                                               null,
    user_social_linkedin            varchar(256)                                               null,
    user_social_vkontakte           varchar(256)                                               null,
    user_profile_background         varchar(256)                                               null,
    user_chat_enabled               enum ('0', '1')                  default '1'               not null,
    user_newsletter_enabled         enum ('0', '1')                  default '1'               not null,
    user_tips_enabled               enum ('0', '1')                  default '1'               not null,
    user_privacy_poke               enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_gifts              enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_wall               enum ('me', 'friends', 'public') default 'friends'         not null,
    user_privacy_gender             enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_birthdate          enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_relationship       enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_basic              enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_work               enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_location           enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_education          enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_other              enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_friends            enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_followers          enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_photos             enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_pages              enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_groups             enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_events             enum ('me', 'friends', 'public') default 'public'          not null,
    user_privacy_subscriptions      enum ('me', 'friends', 'public') default 'public'          not null,
    email_post_likes                enum ('0', '1')                  default '1'               not null,
    email_post_comments             enum ('0', '1')                  default '1'               not null,
    email_post_shares               enum ('0', '1')                  default '1'               not null,
    email_wall_posts                enum ('0', '1')                  default '1'               not null,
    email_mentions                  enum ('0', '1')                  default '1'               not null,
    email_profile_visits            enum ('0', '1')                  default '1'               not null,
    email_friend_requests           enum ('0', '1')                  default '1'               not null,
    email_user_verification         enum ('0', '1')                  default '1'               not null,
    email_user_post_approval        enum ('0', '1')                  default '1'               not null,
    email_admin_verifications       enum ('0', '1')                  default '1'               not null,
    email_admin_post_approval       enum ('0', '1')                  default '1'               not null,
    facebook_connected              enum ('0', '1')                  default '0'               not null,
    facebook_id                     varchar(128)                                               null,
    google_connected                enum ('0', '1')                  default '0'               not null,
    google_id                       varchar(128)                                               null,
    twitter_connected               enum ('0', '1')                  default '0'               not null,
    twitter_id                      varchar(128)                                               null,
    instagram_connected             enum ('0', '1')                  default '0'               not null,
    instagram_id                    varchar(128)                                               null,
    linkedin_connected              enum ('0', '1')                  default '0'               not null,
    linkedin_id                     varchar(128)                                               null,
    vkontakte_connected             enum ('0', '1')                  default '0'               not null,
    vkontakte_id                    varchar(128)                                               null,
    wordpress_connected             enum ('0', '1')                  default '0'               not null,
    wordpress_id                    varchar(128)                                               null,
    sngine_connected                enum ('0', '1')                  default '0'               not null,
    sngine_id                       varchar(128)                                               null,
    user_referrer_id                int                                                        null,
    points_earned                   enum ('0', '1')                  default '0'               not null,
    user_points                     float                            default 0                 not null,
    user_wallet_balance             float                            default 0                 not null,
    user_affiliate_balance          float                            default 0                 not null,
    user_market_balance             float                            default 0                 not null,
    user_funding_balance            float                            default 0                 not null,
    user_monetization_enabled       enum ('0', '1')                  default '0'               not null,
    user_monetization_chat_price    float                            default 0                 not null,
    user_monetization_call_price    float                            default 0                 not null,
    user_monetization_min_price     float                            default 0                 not null,
    user_monetization_plans         int unsigned                     default '0'               not null,
    user_monetization_balance       float                            default 0                 not null,
    chat_sound                      enum ('0', '1')                  default '1'               not null,
    notifications_sound             enum ('0', '1')                  default '1'               not null,
    onesignal_user_id               varchar(100)                                               null,
    user_language                   varchar(16)                      default 'en_us'           null,
    user_free_tried                 enum ('0', '1')                  default '0'               not null,
    coinbase_hash                   varchar(128)                                               null,
    coinbase_code                   varchar(128)                                               null,
    free_posts_usage                int                              default 0                 null,
    constraint facebook_id
        unique (facebook_id),
    constraint google_id
        unique (google_id),
    constraint instagram_id
        unique (instagram_id),
    constraint linkedin_id
        unique (linkedin_id),
    constraint twitter_id
        unique (twitter_id),
    constraint vkontakte_id
        unique (vkontakte_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_album_covers
    on users (user_album_covers);

create index user_album_pictures
    on users (user_album_pictures);

create index user_album_timeline
    on users (user_album_timeline);

create index user_banned
    on users (user_banned);

create index user_country
    on users (user_country);

create index user_cover_id
    on users (user_cover_id);

create index user_firstname_idx
    on users (user_firstname);

create index user_gender
    on users (user_gender);

create index user_id_idx
    on users (user_id);

create index user_lastname_idx
    on users (user_lastname);

create index user_name_idx
    on users (user_name);

create index user_picture_id
    on users (user_picture_id);

create index user_registered
    on users (user_registered);

create index user_subscribed
    on users (user_subscribed);

create table users_accounts
(
    id         int unsigned auto_increment
        primary key,
    user_id    int unsigned not null,
    account_id int unsigned not null,
    constraint user_id_account_id
        unique (user_id, account_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users_addresses
(
    address_id       int unsigned auto_increment
        primary key,
    user_id          int unsigned not null,
    address_title    varchar(256) not null,
    address_country  varchar(256) not null,
    address_city     varchar(256) not null,
    address_zip_code varchar(256) not null,
    address_phone    varchar(256) not null,
    address_details  text         not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on users_addresses (user_id);

create table users_affiliates
(
    id          int unsigned auto_increment
        primary key,
    referrer_id int unsigned not null,
    referee_id  int unsigned not null,
    constraint referrer_id_referee_id
        unique (referrer_id, referee_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users_blocks
(
    id         int unsigned auto_increment
        primary key,
    user_id    int unsigned not null,
    blocked_id int unsigned not null,
    constraint user_id_blocked_id
        unique (user_id, blocked_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users_gifts
(
    id           int unsigned auto_increment
        primary key,
    from_user_id int unsigned not null,
    to_user_id   int unsigned not null,
    gift_id      int unsigned not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index from_user_id
    on users_gifts (from_user_id);

create index gift_id
    on users_gifts (gift_id);

create index to_user_id
    on users_gifts (to_user_id);

create table users_groups
(
    user_group_id        int unsigned auto_increment
        primary key,
    user_group_title     varchar(255) not null,
    permissions_group_id int unsigned not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index permissions_group_id
    on users_groups (permissions_group_id);

create table users_invitations
(
    id              int unsigned auto_increment
        primary key,
    user_id         int unsigned not null,
    email_phone     varchar(64)  not null,
    invitation_date datetime     not null,
    constraint user_id_email_phone
        unique (user_id, email_phone)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users_pokes
(
    id       int unsigned auto_increment
        primary key,
    user_id  int unsigned not null,
    poked_id int unsigned not null,
    constraint user_id_poked_id
        unique (user_id, poked_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users_recurring_payments
(
    id              int unsigned auto_increment
        primary key,
    user_id         int unsigned not null,
    payment_gateway varchar(256) not null,
    handle          varchar(256) not null,
    handle_id       int unsigned not null,
    subscription_id varchar(256) not null,
    time            datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on users_recurring_payments (user_id);

create table users_searches
(
    log_id    int unsigned auto_increment
        primary key,
    user_id   int unsigned not null,
    node_id   int unsigned not null,
    node_type varchar(32)  not null,
    time      datetime     null,
    constraint user_id_node_id_node_type
        unique (user_id, node_id, node_type)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on users_searches (user_id);

create table users_sessions
(
    session_id       int unsigned auto_increment
        primary key,
    session_token    varchar(64)                      not null,
    session_date     datetime                         not null,
    session_type     enum ('W', 'A', 'I') default 'W' not null,
    user_id          int unsigned                     not null,
    user_ip          varchar(64)                      not null,
    user_browser     varchar(64)                      null,
    user_os          varchar(64)                      not null,
    user_os_version  varchar(64)                      null,
    user_device_name varchar(64)                      null,
    constraint session_token
        unique (session_token)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on users_sessions (user_id);

create index user_ip
    on users_sessions (user_ip);

create table users_sms
(
    id          int unsigned auto_increment
        primary key,
    phone       varchar(256) not null,
    insert_date datetime     not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table users_top_friends
(
    id        int unsigned auto_increment
        primary key,
    user_id   int unsigned not null,
    friend_id int unsigned not null,
    constraint user_id_friend_id
        unique (user_id, friend_id)
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table verification_requests
(
    request_id       int unsigned auto_increment
        primary key,
    node_id          int unsigned not null,
    node_type        varchar(32)  not null,
    photo            varchar(256) null,
    passport         varchar(256) null,
    business_website text         null,
    business_address text         null,
    message          text         null,
    time             datetime     not null,
    status           tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create table wallet_payments
(
    payment_id   int auto_increment
        primary key,
    user_id      int unsigned not null,
    amount       varchar(32)  not null,
    method       varchar(64)  not null,
    method_value text         not null,
    time         datetime     not null,
    status       tinyint(1)   not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on wallet_payments (user_id);

create table wallet_transactions
(
    transaction_id int auto_increment
        primary key,
    user_id        int unsigned       not null,
    node_type      varchar(32)        not null,
    node_id        int unsigned       null,
    amount         varchar(32)        not null,
    type           enum ('in', 'out') not null,
    date           datetime           not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

create index user_id
    on wallet_transactions (user_id);

create table widgets
(
    widget_id   int unsigned auto_increment
        primary key,
    title       varchar(256)             not null,
    place       varchar(32)              not null,
    place_order int unsigned default '1' not null,
    code        mediumtext               not null
)
    collate = utf8mb4_general_ci
    row_format = DYNAMIC;

