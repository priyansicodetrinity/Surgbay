// API URL: http://surgbay.com/wp-json/surgbay-api/v1/users/?role=yith_vendor

function get_user_list()
{
    // Get role filter from query (e.g., ?role=yith_vendor)
    $role = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';

    // Get email filter from query (optional)
    $email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

    global $wpdb;

    // Base query to fetch users and their roles (capabilities)
    $query = " SELECT 
        u.ID, 
        u.user_login, 
        u.user_email, 
        u.display_name, 
        u.user_pass, 
        um.meta_value as capabilities
    FROM 
        $wpdb->users u
    LEFT JOIN 
        $wpdb->usermeta um ON u.ID = um.user_id 
    AND 
        um.meta_key = '{$wpdb->prefix}capabilities'";

    // If email is provided, filter user by email
    if (!empty($email)) {
        $query .= $wpdb->prepare(" WHERE u.user_email = %s", $email);
    }

    // Execute query and get results
    $users = $wpdb->get_results($query);

    // Return error if no users found
    if (empty($users)) {
        return new WP_Error('error', 'No users found', array('status' => 404));
    }

    $user_list = array();

    foreach ($users as $user) {

        // Convert serialized capabilities into array (to get roles)
        $roles = maybe_unserialize($user->capabilities);

        // If role filter is applied
        if (!empty($role)) {

            // Check if user has the requested role
            if (is_array($roles) && array_key_exists($role, $roles)) {

                // Get vendor ID linked to this user (YITH Multi Vendor)
                $vendor_user_id = $user->ID;
                $vendor_id = get_user_meta($vendor_user_id, yith_wcmv_get_user_meta_key(), true);

                // Get vendor object
                $vendor = yith_wcmv_get_vendor($vendor_id);

                // Get vendor staff/admin users
                $admins = YITH_Vendors_Staff()->get_vendor_admins($vendor);
                $staff_members = [];

                // If vendor has staff members
                if (!empty($admins)) {

                    // Only include staff if current user is store owner
                    if ($vendor->get_owner() == $user->ID) {

                        foreach ($admins as $staff_id) {

                            // Get staff user details
                            $staff_obj = get_user_by('id', $staff_id);

                            $staff_members[] = array(
                                'ID' => $staff_obj->ID,
                                'username' => $staff_obj->user_login,
                                'email' => $staff_obj->user_email,
                                'display_name' => $staff_obj->display_name,
                                'role' => $staff_obj->roles, // Staff roles
                            );
                        }
                    }
                }

                // Only include vendor if user is the owner of the store
                if ($vendor->get_owner() == $user->ID) {

                    $user_list[] = array(
                        'ID' => $user->ID,
                        'store_name' => $vendor->get_name(), // Vendor store name
                        'username' => $user->user_login,
                        'email' => $user->user_email,
                        'display_name' => $user->display_name,
                        'password' => $user->user_pass, // Hashed password
                        'role' => $role, // Requested role
                        'staff' => $staff_members ? $staff_members : "" // Staff list
                    );
                }
            }

        } else {

            // If no role filter, return all users with basic info
            $user_role = !empty($roles) ? array_keys($roles)[0] : '';

            $user_list[] = array(
                'ID' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'password' => $user->user_pass, // Hashed password
                'role' => $user_role, // First role
            );
        }
    }

    // Return response in REST API format
    return rest_ensure_response($user_list);
}
