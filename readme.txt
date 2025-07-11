=== AI Database Optimizer ===
Contributors: Fulgid
Tags: database, optimization, ai, backup, performance
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered database optimization with automatic backup protection for peak WordPress performance.

== Description ==

AI Database Optimizer uses advanced artificial intelligence techniques to analyze your WordPress database structure, query patterns, and performance metrics to automatically optimize your database for maximum efficiency. With built-in automatic backup protection, you can optimize with confidence knowing your data is always safe.

= Key Features =

* **Automatic Database Backups**: Creates backups before every optimization for complete safety
* **Backup Management**: View, manage, and restore from previous backups with one click
* **AI-Driven Analysis**: Identifies performance bottlenecks and optimization opportunities
* **Smart Indexing**: Creates custom indexes based on your specific query patterns
* **Automated Optimization**: Schedule regular optimizations at your preferred frequency
* **Multiple Optimization Levels**: Choose from low, medium, or high optimization intensity
* **Real-Time Performance Monitoring**: Live database performance charts and metrics
* **Comprehensive Reports**: Get detailed insights about optimizations performed
* **Email Notifications**: Receive reports after each optimization

= Why AI Database Optimizer? =

Unlike traditional database optimization plugins that apply generic optimizations, AI Database Optimizer analyzes your unique database usage patterns and applies custom optimizations specifically tailored to your WordPress site. With automatic backup protection, you get better performance improvements with zero risk of data loss.

= Safety & Security =

* **Automatic Backups**: Every optimization is preceded by a complete database backup
* **One-Click Restore**: Instantly restore your database from any previous backup
* **Backup History**: View and manage all your database backups in one place
* **Smart Cleanup**: Automatically manages backup storage to prevent disk space issues
* **Secure Storage**: Backups are stored securely in your WordPress uploads directory

= Technical Benefits =

* Reduced query execution time
* Lower server resource usage
* Smaller database size
* Improved overall site performance
* Better user experience
* Zero downtime optimization process

== Installation ==

1. Upload the `db_ai_optimizer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Tools > AI DB Optimizer' to configure settings and run your first analysis

== Frequently Asked Questions ==

= Is it safe to use automatic optimization? =

Absolutely! The plugin automatically creates a complete database backup before every optimization operation. This means you can always restore your database to its previous state if needed. Additionally, the plugin uses a conservative approach by default and allows you to choose the optimization level that matches your comfort level.

= How often should I optimize my database? =

For most websites, weekly optimization is recommended. High-traffic sites may benefit from daily optimization, while small sites might only need monthly maintenance.

= Will this plugin work with my hosting provider? =

Yes, AI Database Optimizer works with all major WordPress hosting providers. The plugin respects database resource limitations and performs optimizations in small batches when needed.

= Does this plugin modify my database structure? =

When using medium or high optimization levels, the plugin may add indexes to your database tables to improve performance. These modifications are standard database optimizations and don't affect your data integrity.

= What happens to my backups? =

The plugin automatically creates database backups before each optimization and stores them securely in your WordPress uploads directory. You can view all your backups in the plugin dashboard and restore any backup with a single click. The plugin keeps the 5 most recent backups and automatically cleans up older ones to save disk space.

= Can I undo optimizations if needed? =

Yes! Every optimization operation is preceded by an automatic backup. You can easily restore your database to any previous state using the one-click restore feature in the backup management section. Additionally, any indexes created by the plugin can be removed if necessary through the plugin interface.

== Screenshots ==

1. Dashboard with database status overview and health indicators
2. Real-time performance monitoring with interactive charts  
3. Analysis results showing optimization opportunities
4. Backup management interface with restore options
5. Optimization settings configuration panel
6. Detailed optimization history and performance metrics

== Changelog ==

= 1.1.4 =
* SECURITY: Fixed SQL injection vulnerabilities by properly using wpdb->prepare() for all user inputs
* SECURITY: Enhanced input validation with regex checks for table/column names  
* SECURITY: Added proper caching to all direct database queries per WordPress standards
* SECURITY: Strengthened nonce verification and capability checks across all AJAX handlers
* ENHANCED: Improved WordPress coding standards compliance
* ENHANCED: Better error handling for invalid database identifiers

= 1.1.3 =
* FIXED: Analysis now always gets fresh data by clearing all caches before analysis
* FIXED: Optimization performs real database changes with session-based caching for WordPress compliance
* FIXED: DB health score calculation uses fresh overhead data after optimization
* FIXED: Enhanced cache clearing strategy with time-based keys for better optimization detection
* FIXED: Added debug logging for optimization and backup history tracking
* ENHANCED: WordPress.org compliant caching while ensuring real optimizations are performed
* ENHANCED: Session-based cache keys prevent stale data while maintaining performance

= 1.1.1 =
* FIXED: Removed all dummy/hardcoded data from analysis and recommendations
* FIXED: Query pattern analysis now uses real slow query data instead of simulated patterns
* FIXED: Table correlation analysis now detects actual WordPress table relationships
* FIXED: Comprehensive cache clearing after optimization to ensure fresh analysis results
* FIXED: Enhanced index detection to prevent false positives after optimization
* ENHANCED: AI recommendations now track specific completed optimizations to avoid repetition
* ENHANCED: More accurate optimization tracking with detailed action metadata

= 1.1.0 =
* NEW: Automatic database backup system before every optimization
* NEW: Backup management interface with one-click restore functionality
* NEW: Real-time performance monitoring with interactive charts
* NEW: Database composition analysis with visual charts
* ENHANCED: Improved security with comprehensive SQL injection protection
* ENHANCED: Better error handling and logging throughout the plugin
* FIXED: Updated Chart.js to latest version (v4.5.0) for better compatibility
* FIXED: Replaced inline CSS with proper WordPress enqueue system
* FIXED: Added proper prefixes to all class names for WordPress.org compliance
* FIXED: Optimization history table schema updated with performance data support

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Major update with automatic backup protection! This version adds comprehensive database backup functionality, ensuring your data is always safe during optimization operations. Includes new backup management interface, real-time performance monitoring, and improved security features.

= 1.0.0 =
Initial version of AI Database Optimizer.

== Privacy Policy ==

AI Database Optimizer does not collect any personal data from your visitors. It analyzes only your WordPress database structure and query patterns to provide optimization recommendations. Database backups are stored locally on your server in the WordPress uploads directory and are never transmitted outside your website. The plugin does not send any data outside your website except for email notifications to the address you configure.