<div id="profile">
     <div class="wrap">
          <?php echo staff_profile_image($params['props']->staffid, array('img', 'img-responsive', 'staff-profile-image-small', 'pull-left'), 'small', ['id' => 'profile-img']); ?>
          <p>
               <?php echo get_staff_full_name(); ?>
               <div id="status-options" class="">
                    <ul>
                         <li id="status-online" class="active"><span class="status-circle"></span>
                              <p><?= _l('chat_status_online'); ?></p>
                         </li>
                         <li id="status-away"><span class="status-circle"></span>
                              <p><?= _l('chat_status_away'); ?></p>
                         </li>
                         <li id="status-busy"><span class="status-circle"></span>
                              <p><?= _l('chat_status_busy'); ?></p>
                         </li>
                         <li id="status-offline"><span class="status-circle"></span>
                              <p><?= _l('chat_status_offline'); ?></p>
                         </li>
                    </ul>
               </div>
          </p>
     </div>
</div>