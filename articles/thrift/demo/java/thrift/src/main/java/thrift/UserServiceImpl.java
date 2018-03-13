package thrift;

import Services.UserService.*;
import org.apache.thrift.TException;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class UserServiceImpl implements UserService.Iface{

    @Override
    public UserListResp userList(UserListReq req) throws ApiErrorException, TException {

        List<Integer> uidList = req.uidList;
        if (uidList.isEmpty()) {
            throw new ApiErrorException(ErrCodeEnum.PARAM_ERROR, "参数错误");
        }

        List<UserInfo> lists = getUserByUidArr(uidList);
        if (lists.isEmpty()) {
            throw new ApiErrorException(ErrCodeEnum.SERVER_ERROR, "服务器错误");
        }

        UserListResp result = new UserListResp(lists);
        return result;
    }


    private List<UserInfo> getUserByUidArr(List<Integer> uidList) {

        Map<Integer, UserInfo> userMap = new HashMap<Integer, UserInfo>(){{
            put(1, new UserInfo(1, "用户NO1",(byte) 1, (short) 18));
            put(2,  new UserInfo(2, "用户NO2",(byte) 2, (short) 19));
        }};

        List<UserInfo> lists = new ArrayList<UserInfo>();
        for (Integer uid:uidList) {
            if (userMap.containsKey(uid)) {
                lists.add(userMap.get(uid));
            }
        }

        return lists;
    }
}
