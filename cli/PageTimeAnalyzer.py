# -*- coding: utf-8 -*-

import time
import threading
import re
import memcache
import hashlib
import socket
#from phpserialize import *
#from pprint import pprint
from urlparse import urlparse

class PageTimeAnalyzer(object):

  def __init__(self):

    # this is what will match the apache lines
    # %h %l %u %t "%r" %s %b "%{Referer}i" "%{User-Agent}i" %D
    self.reg = re.compile('(?P<remote_ip>[^ ]+) (?P<user>[^ ]+) (?P<user2>[^ ]+) \[(?P<logdate>[^\]]+)\] (?P<request>[^ ]+) (?P<url>[^ ]+) (?P<http_protocol>[^ ]+) (?P<init_retcode>[^ ]+) (?P<size>[^ ]+) "(?P<referrer>[^"]+)" "(?P<user_agent>[^"]+)" (?P<req_time>[^ ]+)')

    # Set the instance name to short host name
    hostName = socket.gethostname()
    self.instance = hostName.split('.')[0]
    
    self.url_array = {}
    self.url_durations = {}

    # Ignore patterns - this is to identify which URLs are e.g. images since
    # we may want to ignore those URLs
    self.ignore_patterns = "(.png|.jpg|.gif)"

    # Save intermediate values in memcache for 4 hours
    self.MC_TTL = 14400
    
    self.lines_processed = 0
    
    # I have to do this because python 2.4 doesn't support strptime. It is used
    # to convert the date ie. 02/Apr/2010 to 20100402. I didn't want to introduce
    # any dependencies
    self.months_dict = {
     'Jan' : '01', 'Feb' : '02', 'Mar' : '03', 'Apr' : '04', 'May' : '05', 'Jun' : '06',
     'Jul' : '07', 'Aug' : '08', 'Sep' : '09', 'Oct' : '10', 'Nov' : '11', 'Dec' : '12'
    }
    
    
  ###################################################################################
  # Initialize memcache
  ###################################################################################
  def initialize_memcache(self, server = "localhost", port = "11211"):
    self.mc = memcache.Client([server + ':' + port], debug=0)
    
    # Let's keep track of all the instances we are serving those are stored in a 
    # comma delimited string in the instances key
    mc_key = "instances"
    # Keep them forever
    MC_TTL = 0
    
    instance_list_string = self.mc.get(mc_key)
    
    if not instance_list_string:
      # Key was not found
      self.mc.set(mc_key ,self.instance, MC_TTL) 
    else:
      instance_list = instance_list_string.split(",")
      if self.instance not in instance_list:
        instance_list.append(self.instance)
        self.mc.replace(mc_key,  ",".join(instance_list) , MC_TTL)
    
  ###################################################################################
  # Set the instance name to be used. Instance name is usually the hostname
  # or could be anything else ie. web01-8080, web01A
  ###################################################################################
  def set_instance_name(self, instance_name ):
    self.instance = instance_name
 
  ###################################################################################
  # Meat :-) that does the parsing magic
  ###################################################################################
  def parse_line(self, line):
    '''This function should digest the contents of one line at a time,
    updating the internal state variables.'''
    
    try:
      regMatch = self.reg.match(line)
      if regMatch:
        
        linebits = regMatch.groupdict()
        
        rescode = float(linebits['init_retcode'])
        
        isIgnore = re.search( self.ignore_patterns , linebits['url'])
        
	# We don't care for POST requests
	if( 'GET' in linebits['request'] and (rescode >= 200) and (rescode < 300) and isIgnore == None  ):
  
          self.lines_processed += 1
          # capture request duration
          dur = float(linebits['req_time'])
          # Requet times are in microseconds convert to seconds
          dur = dur / 1000000
                
          full_date = linebits['logdate']
          # This is not my proudest code however due to lack of strptime in Python 2.4
          # we have to resort to these kinds of craziness
          date_time_pieces = full_date.split(':')
          date = date_time_pieces[0]
          # Split date 
          split_date = date.split('/')
          day = split_date[0]
          month = split_date[1]
          year = split_date[2]
          # Month will be a word e.g. Jan, Feb. Convert it into a number
          month = self.months_dict[month]
          hour = date_time_pieces[1]
          minute = date_time_pieces[2]
        
          # date and time resolution is date, hour
          date_and_time = year + month + day + hour 

          # Get the URL path ie. strip off any GET arguments
          parsed_url = urlparse(linebits['url'])
          url = parsed_url[2]

          # Calculate MD5 hash of the URL
          url_md5 = hashlib.md5(url).hexdigest()
              
          # We use hash key since we may be processing logs from different time periods
          hash_key = url_md5 + "-" + date_and_time
          #######################################################################################
          # Populate our internal URL cache as well as memcache
          # URL cache has a list of all URLs that have been observed during a particular
          # date and time. 
          #######################################################################################
          # If we have the URL array do nothing
          if hash_key not in self.url_array:
            ###########################################################
            # Try to add MD5 hash to the urlmd5 map
            # urlmd5-abcdefghig which contains the url hashed
            ###########################################################
            self.url_array[hash_key] = url
            mc_key = "url-" + url_md5 
            
            try:
              return_code = self.mc.add(mc_key , url, self.MC_TTL) 
            except Exception, e:
              raise Exception, "ERROR: adding url to MC %s" % e

            self.url_durations[hash_key] = []

          # Add Duration
          self.url_durations[hash_key].append(str(dur))

    except Exception, e:
      raise Exception, "ERROR: contents failed with %s" % e


  ###################################################################################
  # Send data to memcache
  ###################################################################################
  def send_to_memcache(self):
    
    urllist = {}

    for hash in self.url_durations:
      split_hash = hash.split('-')
      url_md5 = split_hash[0]
      date_and_time = split_hash[1]
      duration_string = ",".join(self.url_durations[hash])

      # Add URLs to urllist for date_and_time
      if date_and_time not in urllist: 
        urllist[date_and_time] = []

      if url_md5 not in urllist[date_and_time]:
        urllist[date_and_time].append(url_md5)     
      ##################################################################################
      ## Add the durations to the a url-duration-key for this day and hour
      ##################################################################################
      mc_key = "urldur-" + self.instance + "-" + date_and_time + "-" + url_md5 
      return_code = self.mc.add(mc_key , duration_string , self.MC_TTL) 
      
      # If this key already exists we need to append it
      if ( return_code == 0 ):
        try:
          self.mc.append(mc_key, "," + duration_string , self.MC_TTL)
        except Exception, e:
          raise Exception, "ERROR: can't add durations %s" % e

    # Let's add the date time variables to unprocessed date-time
    for date_and_time in urllist:
      
      DATETIME_MC_TTL = 86400
      mc_key = "unprocessed_date_time"
      date_time_string = self.mc.get(mc_key)
    
      if not date_time_string:
	# Key was not found
	self.mc.set(mc_key, date_and_time, DATETIME_MC_TTL) 
      else:
	date_time_list = date_time_string.split(",")
	if date_and_time not in date_time_list:
	  date_time_list.append(date_and_time)
	  self.mc.replace(mc_key,  ",".join(date_time_list) , DATETIME_MC_TTL)

      # Add URL list to memcached
      mc_key = "urllist-" + date_and_time
      urllist_string = ",".join(urllist[date_and_time])
      return_code = self.mc.add(mc_key , urllist_string, self.MC_TTL)
      if ( return_code == 0 ):
        self.mc.append(mc_key, "," + urllist_string , self.MC_TTL)

