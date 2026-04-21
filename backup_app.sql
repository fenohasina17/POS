--
-- PostgreSQL database dump
--

\restrict 362gE2yxNHXjFbN1pf8QlH51v6CAG4WHofMNhJwOy9VdXoUXhTZVYbnKdbChF3m

-- Dumped from database version 15.17 (Debian 15.17-1.pgdg13+1)
-- Dumped by pg_dump version 15.17 (Debian 15.17-1.pgdg13+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO app;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO app;

--
-- Name: cash_register_sessions; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.cash_register_sessions (
    id bigint NOT NULL,
    cash_register_id bigint NOT NULL,
    user_id bigint NOT NULL,
    closed_by_user_id bigint,
    starting_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    expected_cash_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    actual_cash_amount numeric(12,2),
    difference_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_sales numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_refunds numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    start_ticket_number integer,
    is_closed boolean DEFAULT false NOT NULL,
    is_bill_checked boolean DEFAULT false NOT NULL,
    has_discrepancy boolean DEFAULT false NOT NULL,
    closing_notes text,
    discrepancy_explanation text,
    notes text,
    opened_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    closed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    total_sales_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL
);


ALTER TABLE public.cash_register_sessions OWNER TO app;

--
-- Name: cash_register_sessions_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.cash_register_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.cash_register_sessions_id_seq OWNER TO app;

--
-- Name: cash_register_sessions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.cash_register_sessions_id_seq OWNED BY public.cash_register_sessions.id;


--
-- Name: cash_registers; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.cash_registers (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    point_of_sale_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.cash_registers OWNER TO app;

--
-- Name: cash_registers_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.cash_registers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.cash_registers_id_seq OWNER TO app;

--
-- Name: cash_registers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.cash_registers_id_seq OWNED BY public.cash_registers.id;


--
-- Name: cash_transactions; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.cash_transactions (
    id bigint NOT NULL,
    session_id bigint NOT NULL,
    sale_id bigint,
    type character varying(255) DEFAULT 'sale'::character varying NOT NULL,
    amount numeric(10,2) NOT NULL,
    description character varying(255),
    reference character varying(255),
    created_by bigint,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT cash_transactions_type_check CHECK (((type)::text = ANY ((ARRAY['sale'::character varying, 'refund'::character varying, 'in'::character varying, 'out'::character varying])::text[])))
);


ALTER TABLE public.cash_transactions OWNER TO app;

--
-- Name: cash_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.cash_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.cash_transactions_id_seq OWNER TO app;

--
-- Name: cash_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.cash_transactions_id_seq OWNED BY public.cash_transactions.id;


--
-- Name: categories; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.categories (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    printer_type_id bigint
);


ALTER TABLE public.categories OWNER TO app;

--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.categories_id_seq OWNER TO app;

--
-- Name: categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.categories_id_seq OWNED BY public.categories.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO app;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.failed_jobs_id_seq OWNER TO app;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO app;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO app;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.jobs_id_seq OWNER TO app;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO app;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.migrations_id_seq OWNER TO app;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_permissions OWNER TO app;

--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


ALTER TABLE public.model_has_roles OWNER TO app;

--
-- Name: order_lines; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.order_lines (
    id bigint NOT NULL,
    sale_id bigint NOT NULL,
    product_id bigint NOT NULL,
    quantity integer NOT NULL,
    price numeric(10,2) NOT NULL,
    total numeric(10,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.order_lines OWNER TO app;

--
-- Name: order_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.order_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.order_lines_id_seq OWNER TO app;

--
-- Name: order_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.order_lines_id_seq OWNED BY public.order_lines.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO app;

--
-- Name: payments; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.payments OWNER TO app;

--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.payments_id_seq OWNER TO app;

--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.permissions OWNER TO app;

--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.permissions_id_seq OWNER TO app;

--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO app;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.personal_access_tokens_id_seq OWNER TO app;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: point_of_sale_product; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.point_of_sale_product (
    id bigint NOT NULL,
    point_of_sale_id bigint NOT NULL,
    product_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.point_of_sale_product OWNER TO app;

--
-- Name: point_of_sale_product_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.point_of_sale_product_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.point_of_sale_product_id_seq OWNER TO app;

--
-- Name: point_of_sale_product_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.point_of_sale_product_id_seq OWNED BY public.point_of_sale_product.id;


--
-- Name: point_of_sales; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.point_of_sales (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.point_of_sales OWNER TO app;

--
-- Name: point_of_sales_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.point_of_sales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.point_of_sales_id_seq OWNER TO app;

--
-- Name: point_of_sales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.point_of_sales_id_seq OWNED BY public.point_of_sales.id;


--
-- Name: pricing; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.pricing (
    id bigint NOT NULL,
    point_of_sale_id bigint NOT NULL,
    product_id bigint NOT NULL,
    price numeric(10,2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.pricing OWNER TO app;

--
-- Name: pricing_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.pricing_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.pricing_id_seq OWNER TO app;

--
-- Name: pricing_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.pricing_id_seq OWNED BY public.pricing.id;


--
-- Name: printer_types; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.printer_types (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.printer_types OWNER TO app;

--
-- Name: printer_types_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.printer_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.printer_types_id_seq OWNER TO app;

--
-- Name: printer_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.printer_types_id_seq OWNED BY public.printer_types.id;


--
-- Name: printers; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.printers (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    cash_register_id bigint NOT NULL,
    connection_type character varying(255) DEFAULT 'network'::character varying NOT NULL,
    ip_address character varying(255),
    timeout integer DEFAULT 30 NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    printer_type_id bigint,
    port integer,
    usb_identifier character varying(255),
    CONSTRAINT printers_connection_type_check CHECK (((connection_type)::text = ANY ((ARRAY['network'::character varying, 'usb'::character varying, 'cups'::character varying])::text[])))
);


ALTER TABLE public.printers OWNER TO app;

--
-- Name: printers_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.printers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.printers_id_seq OWNER TO app;

--
-- Name: printers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.printers_id_seq OWNED BY public.printers.id;


--
-- Name: products; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.products (
    id bigint NOT NULL,
    category_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    ref character varying(255) NOT NULL,
    image character varying(255) DEFAULT 'default-product-image.jpg'::character varying,
    status boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.products OWNER TO app;

--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.products_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.products_id_seq OWNER TO app;

--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


ALTER TABLE public.role_has_permissions OWNER TO app;

--
-- Name: roles; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.roles OWNER TO app;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.roles_id_seq OWNER TO app;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sale_payments; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.sale_payments (
    id bigint NOT NULL,
    sale_id bigint NOT NULL,
    payment_id bigint NOT NULL,
    amount numeric(10,2) NOT NULL,
    reference character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.sale_payments OWNER TO app;

--
-- Name: sale_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.sale_payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.sale_payments_id_seq OWNER TO app;

--
-- Name: sale_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.sale_payments_id_seq OWNED BY public.sale_payments.id;


--
-- Name: sales; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.sales (
    id bigint NOT NULL,
    ticket_number character varying(255) NOT NULL,
    user_id bigint NOT NULL,
    point_of_sale_id bigint NOT NULL,
    table_id bigint,
    cash_register_session_id bigint,
    total_amount numeric(10,2) NOT NULL,
    discount_percentage numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    final_amount numeric(10,2) NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    payment_id bigint,
    payment_reference character varying(255),
    amount_received numeric(10,2),
    change_amount numeric(10,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.sales OWNER TO app;

--
-- Name: sales_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.sales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.sales_id_seq OWNER TO app;

--
-- Name: sales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.sales_id_seq OWNED BY public.sales.id;


--
-- Name: session_closures; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.session_closures (
    id bigint NOT NULL,
    session_id bigint NOT NULL,
    closed_by_user_id bigint NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.session_closures OWNER TO app;

--
-- Name: session_closures_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.session_closures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.session_closures_id_seq OWNER TO app;

--
-- Name: session_closures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.session_closures_id_seq OWNED BY public.session_closures.id;


--
-- Name: session_discrepancies; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.session_discrepancies (
    id bigint NOT NULL,
    session_id bigint NOT NULL,
    difference_amount numeric(10,2) NOT NULL,
    explanation text,
    is_checked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.session_discrepancies OWNER TO app;

--
-- Name: session_discrepancies_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.session_discrepancies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.session_discrepancies_id_seq OWNER TO app;

--
-- Name: session_discrepancies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.session_discrepancies_id_seq OWNED BY public.session_discrepancies.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO app;

--
-- Name: tables; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.tables (
    id bigint NOT NULL,
    table_number character varying(255) NOT NULL,
    name character varying(255),
    capacity integer DEFAULT 4 NOT NULL,
    status character varying(255) DEFAULT 'available'::character varying NOT NULL,
    description text,
    point_of_sale_id bigint NOT NULL,
    location json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.tables OWNER TO app;

--
-- Name: tables_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.tables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.tables_id_seq OWNER TO app;

--
-- Name: tables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.tables_id_seq OWNED BY public.tables.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: app
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    point_of_sale_id bigint
);


ALTER TABLE public.users OWNER TO app;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: app
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.users_id_seq OWNER TO app;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: app
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: cash_register_sessions id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_register_sessions ALTER COLUMN id SET DEFAULT nextval('public.cash_register_sessions_id_seq'::regclass);


--
-- Name: cash_registers id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_registers ALTER COLUMN id SET DEFAULT nextval('public.cash_registers_id_seq'::regclass);


--
-- Name: cash_transactions id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_transactions ALTER COLUMN id SET DEFAULT nextval('public.cash_transactions_id_seq'::regclass);


--
-- Name: categories id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.categories ALTER COLUMN id SET DEFAULT nextval('public.categories_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: order_lines id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.order_lines ALTER COLUMN id SET DEFAULT nextval('public.order_lines_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: point_of_sale_product id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sale_product ALTER COLUMN id SET DEFAULT nextval('public.point_of_sale_product_id_seq'::regclass);


--
-- Name: point_of_sales id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sales ALTER COLUMN id SET DEFAULT nextval('public.point_of_sales_id_seq'::regclass);


--
-- Name: pricing id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.pricing ALTER COLUMN id SET DEFAULT nextval('public.pricing_id_seq'::regclass);


--
-- Name: printer_types id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printer_types ALTER COLUMN id SET DEFAULT nextval('public.printer_types_id_seq'::regclass);


--
-- Name: printers id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printers ALTER COLUMN id SET DEFAULT nextval('public.printers_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: sale_payments id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sale_payments ALTER COLUMN id SET DEFAULT nextval('public.sale_payments_id_seq'::regclass);


--
-- Name: sales id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales ALTER COLUMN id SET DEFAULT nextval('public.sales_id_seq'::regclass);


--
-- Name: session_closures id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_closures ALTER COLUMN id SET DEFAULT nextval('public.session_closures_id_seq'::regclass);


--
-- Name: session_discrepancies id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_discrepancies ALTER COLUMN id SET DEFAULT nextval('public.session_discrepancies_id_seq'::regclass);


--
-- Name: tables id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.tables ALTER COLUMN id SET DEFAULT nextval('public.tables_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: cash_register_sessions; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.cash_register_sessions (id, cash_register_id, user_id, closed_by_user_id, starting_amount, expected_cash_amount, actual_cash_amount, difference_amount, total_sales, total_refunds, start_ticket_number, is_closed, is_bill_checked, has_discrepancy, closing_notes, discrepancy_explanation, notes, opened_at, closed_at, created_at, updated_at, deleted_at, total_sales_amount) FROM stdin;
\.


--
-- Data for Name: cash_registers; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.cash_registers (id, name, point_of_sale_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cash_transactions; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.cash_transactions (id, session_id, sale_id, type, amount, description, reference, created_by, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: categories; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.categories (id, name, created_at, updated_at, printer_type_id) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2025_04_09_120712_create_personal_access_tokens_table	1
5	2025_04_14_114938_create_point_of_sales_table	1
6	2025_04_14_115146_add_point_of_sale_id_to_users_table	1
7	2025_04_15_094422_create_category_table	1
8	2025_04_16_054238_create_products_table	1
9	2025_04_16_074845_create_pricing_table	1
10	2025_04_27_124531_create_payments_table	1
11	2025_04_28_075110_create_sales_table	1
12	2025_04_28_105911_create_order_lines_table	1
13	2025_04_29_000000_update_sales_table_add_references	1
14	2025_05_08_054555_create_cash_registers_table	1
15	2025_05_13_063804_create_permission_tables	1
16	2025_06_10_000001_create_printers_table	1
17	2025_06_11_000000_create_point_of_sale_product_table	1
18	2025_07_01_071828_create_cash_register_sessions_table	1
19	2025_07_01_071944_create_cash_transactions_table	1
20	2025_07_01_072041_create_session_discrepancies_table	1
21	2025_07_01_072115_create_session_closures_table	1
22	2025_07_15_000000_add_cash_register_session_id_to_sales_table	1
23	2025_09_10_121610_add_printer_type_to_printers_table	1
24	2025_09_15_112106_create_printer_types_table	1
25	2025_09_15_112422_add_printer_type_id_to_categories_table	2
26	2025_09_15_112628_add_printer_type_id_to_printers_table	2
27	2025_09_22_000001_add_unique_name_to_cash_registers_table	2
28	2025_09_22_124146_create_tables_table	2
29	2025_09_22_124444_add_table_id_to_sales_table	2
30	2025_09_22_125503_add_table_id_to_sales_fillable	2
31	2025_09_22_155737_make_payment_id_nullable_in_sales_table	2
32	2025_10_07_124745_add_amount_fields_to_sales_table	2
33	2025_10_09_000001_update_ticket_number_index_on_sales_table	2
34	2025_10_11_000001_add_registration_fields_to_cash_registers_table	2
35	2025_10_15_000000_remove_registration_columns_from_cash_registers_table	2
36	2025_11_07_125411_update_printers_connection_type	2
37	2026_03_13_081855_create_salepayments_table	2
38	2026_03_13_090000_add_cash_transaction_permissions	2
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.model_has_permissions (permission_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.model_has_roles (role_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: order_lines; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.order_lines (id, sale_id, product_id, quantity, price, total, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.payments (id, name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
1	create.cash_transactions	api	2026-04-20 05:56:40	2026-04-20 05:56:40
2	delete.transactions	api	2026-04-20 05:56:40	2026-04-20 05:56:40
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: point_of_sale_product; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.point_of_sale_product (id, point_of_sale_id, product_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: point_of_sales; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.point_of_sales (id, name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: pricing; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.pricing (id, point_of_sale_id, product_id, price, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: printer_types; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.printer_types (id, name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: printers; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.printers (id, name, cash_register_id, connection_type, ip_address, timeout, is_default, is_active, created_at, updated_at, printer_type_id, port, usb_identifier) FROM stdin;
\.


--
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.products (id, category_id, name, ref, image, status, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sale_payments; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.sale_payments (id, sale_id, payment_id, amount, reference, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sales; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.sales (id, ticket_number, user_id, point_of_sale_id, table_id, cash_register_session_id, total_amount, discount_percentage, final_amount, status, payment_id, payment_reference, amount_received, change_amount, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: session_closures; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.session_closures (id, session_id, closed_by_user_id, notes, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: session_discrepancies; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.session_discrepancies (id, session_id, difference_amount, explanation, is_checked, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- Data for Name: tables; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.tables (id, table_number, name, capacity, status, description, point_of_sale_id, location, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: app
--

COPY public.users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, point_of_sale_id) FROM stdin;
\.


--
-- Name: cash_register_sessions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.cash_register_sessions_id_seq', 1, false);


--
-- Name: cash_registers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.cash_registers_id_seq', 1, false);


--
-- Name: cash_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.cash_transactions_id_seq', 1, false);


--
-- Name: categories_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.categories_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.migrations_id_seq', 38, true);


--
-- Name: order_lines_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.order_lines_id_seq', 1, false);


--
-- Name: payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.payments_id_seq', 1, false);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.permissions_id_seq', 2, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 1, false);


--
-- Name: point_of_sale_product_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.point_of_sale_product_id_seq', 1, false);


--
-- Name: point_of_sales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.point_of_sales_id_seq', 1, false);


--
-- Name: pricing_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.pricing_id_seq', 1, false);


--
-- Name: printer_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.printer_types_id_seq', 1, false);


--
-- Name: printers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.printers_id_seq', 1, false);


--
-- Name: products_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.products_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.roles_id_seq', 1, false);


--
-- Name: sale_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.sale_payments_id_seq', 1, false);


--
-- Name: sales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.sales_id_seq', 1, false);


--
-- Name: session_closures_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.session_closures_id_seq', 1, false);


--
-- Name: session_discrepancies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.session_discrepancies_id_seq', 1, false);


--
-- Name: tables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.tables_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: app
--

SELECT pg_catalog.setval('public.users_id_seq', 1, false);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: cash_register_sessions cash_register_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_register_sessions
    ADD CONSTRAINT cash_register_sessions_pkey PRIMARY KEY (id);


--
-- Name: cash_registers cash_registers_name_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_name_unique UNIQUE (name);


--
-- Name: cash_registers cash_registers_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_pkey PRIMARY KEY (id);


--
-- Name: cash_transactions cash_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_transactions
    ADD CONSTRAINT cash_transactions_pkey PRIMARY KEY (id);


--
-- Name: categories categories_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: order_lines order_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.order_lines
    ADD CONSTRAINT order_lines_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: point_of_sale_product point_of_sale_product_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sale_product
    ADD CONSTRAINT point_of_sale_product_pkey PRIMARY KEY (id);


--
-- Name: point_of_sale_product point_of_sale_product_point_of_sale_id_product_id_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sale_product
    ADD CONSTRAINT point_of_sale_product_point_of_sale_id_product_id_unique UNIQUE (point_of_sale_id, product_id);


--
-- Name: point_of_sales point_of_sales_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sales
    ADD CONSTRAINT point_of_sales_pkey PRIMARY KEY (id);


--
-- Name: pricing pricing_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.pricing
    ADD CONSTRAINT pricing_pkey PRIMARY KEY (id);


--
-- Name: printer_types printer_types_name_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printer_types
    ADD CONSTRAINT printer_types_name_unique UNIQUE (name);


--
-- Name: printer_types printer_types_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printer_types
    ADD CONSTRAINT printer_types_pkey PRIMARY KEY (id);


--
-- Name: printers printers_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_pkey PRIMARY KEY (id);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sale_payments sale_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sale_payments
    ADD CONSTRAINT sale_payments_pkey PRIMARY KEY (id);


--
-- Name: sales sales_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_pkey PRIMARY KEY (id);


--
-- Name: sales sales_session_ticket_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_session_ticket_unique UNIQUE (cash_register_session_id, ticket_number);


--
-- Name: session_closures session_closures_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_closures
    ADD CONSTRAINT session_closures_pkey PRIMARY KEY (id);


--
-- Name: session_discrepancies session_discrepancies_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_discrepancies
    ADD CONSTRAINT session_discrepancies_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: tables tables_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.tables
    ADD CONSTRAINT tables_pkey PRIMARY KEY (id);


--
-- Name: tables tables_point_of_sale_id_table_number_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.tables
    ADD CONSTRAINT tables_point_of_sale_id_table_number_unique UNIQUE (point_of_sale_id, table_number);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cash_register_sessions_cash_register_id_is_closed_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX cash_register_sessions_cash_register_id_is_closed_index ON public.cash_register_sessions USING btree (cash_register_id, is_closed);


--
-- Name: cash_register_sessions_user_id_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX cash_register_sessions_user_id_index ON public.cash_register_sessions USING btree (user_id);


--
-- Name: cash_transactions_created_at_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX cash_transactions_created_at_index ON public.cash_transactions USING btree (created_at);


--
-- Name: cash_transactions_sale_id_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX cash_transactions_sale_id_index ON public.cash_transactions USING btree (sale_id);


--
-- Name: cash_transactions_session_id_type_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX cash_transactions_session_id_type_index ON public.cash_transactions USING btree (session_id, type);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: sales_table_id_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX sales_table_id_index ON public.sales USING btree (table_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: tables_point_of_sale_id_status_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX tables_point_of_sale_id_status_index ON public.tables USING btree (point_of_sale_id, status);


--
-- Name: tables_table_number_index; Type: INDEX; Schema: public; Owner: app
--

CREATE INDEX tables_table_number_index ON public.tables USING btree (table_number);


--
-- Name: cash_register_sessions cash_register_sessions_cash_register_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_register_sessions
    ADD CONSTRAINT cash_register_sessions_cash_register_id_foreign FOREIGN KEY (cash_register_id) REFERENCES public.cash_registers(id) ON DELETE RESTRICT;


--
-- Name: cash_register_sessions cash_register_sessions_closed_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_register_sessions
    ADD CONSTRAINT cash_register_sessions_closed_by_user_id_foreign FOREIGN KEY (closed_by_user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: cash_register_sessions cash_register_sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_register_sessions
    ADD CONSTRAINT cash_register_sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: cash_registers cash_registers_point_of_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_registers
    ADD CONSTRAINT cash_registers_point_of_sale_id_foreign FOREIGN KEY (point_of_sale_id) REFERENCES public.point_of_sales(id) ON DELETE CASCADE;


--
-- Name: cash_transactions cash_transactions_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_transactions
    ADD CONSTRAINT cash_transactions_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: cash_transactions cash_transactions_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_transactions
    ADD CONSTRAINT cash_transactions_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE SET NULL;


--
-- Name: cash_transactions cash_transactions_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.cash_transactions
    ADD CONSTRAINT cash_transactions_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.cash_register_sessions(id) ON DELETE CASCADE;


--
-- Name: categories categories_printer_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.categories
    ADD CONSTRAINT categories_printer_type_id_foreign FOREIGN KEY (printer_type_id) REFERENCES public.printer_types(id) ON DELETE SET NULL;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: order_lines order_lines_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.order_lines
    ADD CONSTRAINT order_lines_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: order_lines order_lines_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.order_lines
    ADD CONSTRAINT order_lines_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: point_of_sale_product point_of_sale_product_point_of_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sale_product
    ADD CONSTRAINT point_of_sale_product_point_of_sale_id_foreign FOREIGN KEY (point_of_sale_id) REFERENCES public.point_of_sales(id) ON DELETE CASCADE;


--
-- Name: point_of_sale_product point_of_sale_product_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.point_of_sale_product
    ADD CONSTRAINT point_of_sale_product_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: pricing pricing_point_of_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.pricing
    ADD CONSTRAINT pricing_point_of_sale_id_foreign FOREIGN KEY (point_of_sale_id) REFERENCES public.point_of_sales(id) ON DELETE CASCADE;


--
-- Name: pricing pricing_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.pricing
    ADD CONSTRAINT pricing_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: printers printers_cash_register_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_cash_register_id_foreign FOREIGN KEY (cash_register_id) REFERENCES public.cash_registers(id) ON DELETE CASCADE;


--
-- Name: printers printers_printer_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.printers
    ADD CONSTRAINT printers_printer_type_id_foreign FOREIGN KEY (printer_type_id) REFERENCES public.printer_types(id) ON DELETE SET NULL;


--
-- Name: products products_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_category_id_foreign FOREIGN KEY (category_id) REFERENCES public.categories(id);


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: sale_payments sale_payments_payment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sale_payments
    ADD CONSTRAINT sale_payments_payment_id_foreign FOREIGN KEY (payment_id) REFERENCES public.payments(id);


--
-- Name: sale_payments sale_payments_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sale_payments
    ADD CONSTRAINT sale_payments_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: sales sales_cash_register_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_cash_register_session_id_foreign FOREIGN KEY (cash_register_session_id) REFERENCES public.cash_register_sessions(id) ON DELETE SET NULL;


--
-- Name: sales sales_payment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_payment_id_foreign FOREIGN KEY (payment_id) REFERENCES public.payments(id) ON DELETE CASCADE;


--
-- Name: sales sales_point_of_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_point_of_sale_id_foreign FOREIGN KEY (point_of_sale_id) REFERENCES public.point_of_sales(id) ON DELETE CASCADE;


--
-- Name: sales sales_table_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_table_id_foreign FOREIGN KEY (table_id) REFERENCES public.tables(id) ON DELETE SET NULL;


--
-- Name: sales sales_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: session_closures session_closures_closed_by_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_closures
    ADD CONSTRAINT session_closures_closed_by_user_id_foreign FOREIGN KEY (closed_by_user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: session_closures session_closures_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_closures
    ADD CONSTRAINT session_closures_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.cash_register_sessions(id) ON DELETE CASCADE;


--
-- Name: session_discrepancies session_discrepancies_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.session_discrepancies
    ADD CONSTRAINT session_discrepancies_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.cash_register_sessions(id) ON DELETE CASCADE;


--
-- Name: tables tables_point_of_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.tables
    ADD CONSTRAINT tables_point_of_sale_id_foreign FOREIGN KEY (point_of_sale_id) REFERENCES public.point_of_sales(id) ON DELETE CASCADE;


--
-- Name: users users_point_of_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: app
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_point_of_sale_id_foreign FOREIGN KEY (point_of_sale_id) REFERENCES public.point_of_sales(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict 362gE2yxNHXjFbN1pf8QlH51v6CAG4WHofMNhJwOy9VdXoUXhTZVYbnKdbChF3m

